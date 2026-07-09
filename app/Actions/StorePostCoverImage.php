<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Normalise an uploaded news-post cover into a 1200×630 JPEG and persist it.
 *
 * The one file doubles as the story page's cover and its og:image, which drives
 * both choices: 1200×630 is the standard 1.91:1 share-card size, and JPEG (not
 * WebP like the truck pipeline) is the one format every link-preview crawler
 * renders — at quality 75 it stays under the ~300 KB cap the strictest crawler
 * (WhatsApp) enforces. The re-encode strips EXIF and the original bytes, same
 * as StoreTruckImage; the uuid name is content-unique, keeping the S3 disk's
 * immutable CacheControl option safe. Returns the stored path — the caller owns
 * updating the model and deleting any replaced file.
 */
final class StorePostCoverImage
{
    private const int WIDTH = 1200;

    private const int HEIGHT = 630;

    private const int QUALITY = 75;

    public function __invoke(Post $post, UploadedFile $file): string
    {
        $image = (new ImageManager(Driver::class))
            ->decodePath($file->getRealPath())
            ->cover(self::WIDTH, self::HEIGHT);

        $encoded = $image->encode(new JpegEncoder(quality: self::QUALITY));

        $path = "post-covers/{$post->id}/".Str::uuid()->toString().'.jpg';
        Storage::disk(config('filesystems.public_disk'))->put($path, (string) $encoded);

        return $path;
    }
}
