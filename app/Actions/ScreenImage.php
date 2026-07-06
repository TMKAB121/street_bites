<?php

declare(strict_types=1);

namespace App\Actions;

use Aws\Rekognition\RekognitionClient;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Screen an uploaded image for explicit/offensive content via AWS Rekognition's
 * DetectModerationLabels. Returns the moderation labels that tripped the
 * confidence threshold; an empty result means "clean".
 *
 * Screening is fail-open by design: disabled by config (local/CI never call
 * AWS), or an error reaching Rekognition, both return "clean". We would rather
 * let a rare image through to the reactive admin queue than block every vendor's
 * upload on an external outage. The source is re-encoded to JPEG first because
 * Rekognition accepts only JPEG/PNG bytes (our stored gallery images are WebP).
 */
class ScreenImage
{
    /**
     * @param  string  $sourcePath  filesystem path to the image to screen
     * @return list<string> moderation labels (empty = passed)
     */
    public function __invoke(string $sourcePath): array
    {
        if (! config('moderation.rekognition.enabled')) {
            return [];
        }

        try {
            $jpeg = (string) (new ImageManager(Driver::class))
                ->decodePath($sourcePath)
                ->encode(new JpegEncoder);

            $result = $this->client()->detectModerationLabels([
                'Image' => ['Bytes' => $jpeg],
                'MinConfidence' => config('moderation.rekognition.min_confidence'),
            ]);

            /** @var list<array{Name?: string}> $labels */
            $labels = $result['ModerationLabels'] ?? [];

            $names = [];
            foreach ($labels as $label) {
                if (isset($label['Name']) && $label['Name'] !== '') {
                    $names[] = $label['Name'];
                }
            }

            return array_values(array_unique($names));
        } catch (Throwable $e) {
            // Fail open — never block an upload on a screening outage. The admin
            // queue still surfaces newly published trucks for a human pass.
            report($e);

            return [];
        }
    }

    private function client(): RekognitionClient
    {
        return new RekognitionClient([
            'version' => 'latest',
            'region' => config('moderation.rekognition.region'),
            // Credentials come from the default AWS chain (ECS task role in
            // prod) — the same chain the S3 disk and SES mailer already use.
        ]);
    }
}
