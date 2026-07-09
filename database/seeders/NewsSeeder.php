<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\StorePostCoverImage;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Seeds the news & events section: six published posts (staggered publish
 * dates over recent weeks, two of them events — one upcoming, one past) plus
 * one draft, all authored by the DatabaseSeeder smoke-test account. Bodies are
 * real Markdown (headings, lists, links, emphasis) so /news and the story
 * pages demo the safe-mode rendering out of the box. A few posts get covers by
 * running fixture images through StorePostCoverImage (the same pipeline the
 * admin form uses — 1200×630 JPEG). Re-running creates duplicates; use
 * migrate:fresh --seed.
 */
class NewsSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $imagePaths = collect(glob(database_path('seeders/fixtures/images/*')))
            ->filter(fn (string $p): bool => is_file($p))
            ->values();

        $posts = [
            [
                'title' => 'Street Bites is live in Kansas City',
                'body' => "We flipped the switch: **Street Bites is live** across the KC metro.\n\n"
                    ."## What you can do today\n\n"
                    ."- Find trucks near you on the live map\n"
                    ."- Check menus and today's hours before you head out\n"
                    ."- Star your favorites so they're one tap away\n\n"
                    .'Vendors: adding your truck takes about two minutes from your profile page.',
                'published_at' => now()->subWeeks(5),
                'cover' => true,
            ],
            [
                'title' => 'Six new trucks joined this month',
                'body' => "The lineup keeps growing — six new kitchens rolled onto the map this month.\n\n"
                    .'From wood-fired pizza in the Crossroads to Korean BBQ bowls out at the Legends, '
                    ."there's a lot of new ground to cover. *Open-now* trucks always sort to the top, "
                    .'so check the map around lunch.',
                'published_at' => now()->subWeeks(4),
                'cover' => true,
            ],
            [
                'title' => 'Food Truck Friday at the City Market',
                'body' => "The season's first **Food Truck Friday** takes over the City Market.\n\n"
                    ."## The lineup\n\n"
                    ."- Smokin' Wheels BBQ\n"
                    ."- Taco Libre\n"
                    ."- Waffle Wagon\n\n"
                    .'Live music from 6, trucks serving until close. Bring cash for shorter lines.',
                'published_at' => now()->subWeeks(3),
                'event_date' => now()->subWeeks(2)->toDateString(),
                'event_location' => 'City Market, River Market',
                'cover' => true,
            ],
            [
                'title' => 'How we pick the Popular lineup',
                'body' => "Ever wonder what puts a truck in the **Popular** carousel?\n\n"
                    ."It's your stars. Every favorite counts as a vote, and the ten most-starred "
                    ."trucks make the row — open-now trucks first. No paid placement, no editor's "
                    ."thumb on the scale.\n\n"
                    .'So if your go-to truck deserves the spotlight: star it.',
                'published_at' => now()->subWeeks(2),
            ],
            [
                'title' => 'Vendor tip: go live the moment you park',
                'body' => 'The single biggest thing a vendor can do on Street Bites: tap **Now Open** '
                    ."when you park.\n\n"
                    ."1. Open your truck from the profile page\n"
                    ."2. Tap *Set my location* so the pin lands where you are\n"
                    ."3. Tap *Now Open*\n\n"
                    .'Your truck jumps to the front of every list and lights up on the map.',
                'published_at' => now()->subWeek(),
            ],
            [
                'title' => 'Summer Street Eats Festival',
                'body' => 'Mark the calendar: the **Summer Street Eats Festival** brings twenty-plus '
                    ."trucks together for one weekend.\n\n"
                    .'Wristbands get you tasting portions across every truck; kids eat free before '
                    ."noon. Watch this page — we'll post the full lineup as trucks confirm.",
                'published_at' => now()->subDays(3),
                'event_date' => now()->addWeeks(3)->toDateString(),
                'event_location' => 'Shawnee Mission Park',
                'cover' => true,
            ],
            [
                'title' => 'Draft: rainy-day guide',
                'body' => 'Where to find covered seating near the regular truck spots. *Still gathering photos.*',
                'draft' => true,
            ],
        ];

        $storeCover = new StorePostCoverImage;
        $coverIndex = 0;

        foreach ($posts as $data) {
            $post = Post::query()->create([
                'user_id' => $author->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'event_date' => $data['event_date'] ?? null,
                'event_location' => $data['event_location'] ?? null,
                'is_published' => ! ($data['draft'] ?? false),
                'published_at' => $data['published_at'] ?? null,
            ]);

            if (($data['cover'] ?? false) && $imagePaths->isNotEmpty()) {
                $this->attachCover($storeCover, $post, $imagePaths[$coverIndex % $imagePaths->count()]);
                $coverIndex++;
            }
        }
    }

    /**
     * Wrap a fixture file in an UploadedFile (test mode skips the
     * is_uploaded_file check) and run it through the same StorePostCoverImage
     * pipeline the admin form uses. Unsupported formats (e.g. AVIF when GD
     * lacks libavif) are skipped with a warning rather than aborting.
     */
    private function attachCover(StorePostCoverImage $action, Post $post, string $path): void
    {
        $mimeType = mime_content_type($path) ?: 'image/jpeg';

        $file = new UploadedFile(
            path: $path,
            originalName: basename($path),
            mimeType: $mimeType,
            test: true,
        );

        try {
            $post->update(['cover_image_path' => $action($post, $file)]);
        } catch (\Throwable $e) {
            $this->command->warn('  Skipped cover '.basename($path).': '.$e->getMessage());
        }
    }
}
