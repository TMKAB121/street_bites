<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FoodTruck;
use App\Models\TruckImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Normalise an uploaded truck image into a 250x250 WebP and persist it.
 *
 * Every upload is centre-cropped to a 1:1 square (we hint the vendor to supply a
 * square, but never trust it), downsized to 250px, and re-encoded as WebP. The
 * re-encode also drops the original bytes — stripping EXIF/GPS metadata, which is
 * both a privacy win and a defence against malicious payloads in image files.
 */
final class StoreTruckImage
{
    private const SIZE = 250;

    private const QUALITY = 80;

    public function __invoke(FoodTruck $truck, UploadedFile $file): TruckImage
    {
        $image = (new ImageManager(Driver::class))
            ->decodePath($file->getRealPath())
            ->cover(self::SIZE, self::SIZE);

        $encoded = $image->encode(new WebpEncoder(quality: self::QUALITY));

        $path = "truck-images/{$truck->id}/".Str::uuid()->toString().'.webp';
        Storage::disk('public')->put($path, (string) $encoded);

        return $truck->images()->create([
            'path' => $path,
            'sort_order' => (int) $truck->images()->max('sort_order') + 1,
        ]);
    }
}
