<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\GeocodeSearch;
use App\Actions\ScreenText;
use App\Actions\StoreTruckImage;
use App\Enums\SocialPlatform;
use App\Models\FoodTruck;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-time bulk importer for unclaimed food trucks — seed the map from public
 * data (Google/Yelp exports, hand-built lists) so the site isn't empty while we
 * recruit real vendors. Every imported truck is created UNCLAIMED (user_id null),
 * published if it passes the text screen (held for admin review if not); its real
 * owner can later claim it from the detail page.
 *
 * CSV format — a header row is required; multi-value cells are pipe-separated:
 *
 *   name          (required) truck name; also the duplicate key
 *   description   free text
 *   address       free-text address, geocoded when latitude/longitude are absent
 *   latitude      decimal; with longitude, skips geocoding
 *   longitude     decimal
 *   tags          "Mexican|Tacos"
 *   menu          "Al Pastor Taco=4.50|Chips & Guac=6|Agua Fresca"  (=price optional)
 *   images        "img/taco.jpg|/abs/truck.jpg"  (relative paths resolve to the CSV's dir)
 *   social_urls   "https://instagram.com/x|https://facebook.com/x"
 *   timezone      IANA id (e.g. America/Chicago); defaults to the app timezone
 *
 * A row with neither coordinates nor a geocodable address imports UNPINNED — the
 * app fully supports pinless trucks (map/directions/distance simply hide). No
 * operating hours are imported (they're per-business-date); imported trucks show
 * "hours not posted" until a vendor claims and sets them.
 *
 * Nominatim's usage policy caps geocoding at ~1 request/second, so the command
 * sleeps between network lookups — expect large address-only imports to be slow.
 */
class ImportTrucks extends Command
{
    protected $signature = 'trucks:import {path : Path to the CSV file} {--dry-run : Parse and report without writing or geocoding}';

    protected $description = 'Import unclaimed food trucks from a CSV of public data';

    /** @var list<string> */
    private const REQUIRED_HEADERS = ['name'];

    private int $created = 0;

    private int $skippedDuplicate = 0;

    private int $held = 0;

    private int $geocodeMisses = 0;

    private int $errors = 0;

    public function handle(GeocodeSearch $geocode, ScreenText $screenText, StoreTruckImage $storeImage): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read CSV file: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Cannot open CSV file: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->error('CSV file is empty.');

            return self::FAILURE;
        }

        /** @var list<string> $columns */
        $columns = array_map(fn ($h): string => mb_strtolower(trim((string) $h)), $header);

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $columns, true)) {
                fclose($handle);
                $this->error("CSV is missing the required '{$required}' column.");

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $csvDir = dirname($path);

        if ($dryRun) {
            $this->info('Dry run — no data will be written and no addresses geocoded.');
        }

        while (($record = fgetcsv($handle)) !== false) {
            /** @var array<string, string> $row */
            $row = $this->mapRow($columns, $record);

            try {
                $this->importRow($row, $geocode, $screenText, $storeImage, $csvDir, $dryRun);
            } catch (Throwable $e) {
                $this->errors++;
                $this->warn('  Error: '.$e->getMessage());
            }
        }

        fclose($handle);

        $this->summary();

        return self::SUCCESS;
    }

    /**
     * Pair the header columns with a data record into a name-keyed row, tolerant
     * of short/long records.
     *
     * @param  list<string>  $columns
     * @param  list<string|null>  $record
     * @return array<string, string>
     */
    private function mapRow(array $columns, array $record): array
    {
        $row = [];
        foreach ($columns as $i => $column) {
            $row[$column] = trim((string) ($record[$i] ?? ''));
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(
        array $row,
        GeocodeSearch $geocode,
        ScreenText $screenText,
        StoreTruckImage $storeImage,
        string $csvDir,
        bool $dryRun,
    ): void {
        $name = $row['name'] ?? '';
        if ($name === '') {
            $this->warn('  Skipped a row with no name.');

            return;
        }

        // Dedup by name (case-insensitive via the column collation), including
        // soft-deleted trucks so admin-removed listings don't resurrect.
        if (FoodTruck::withTrashed()->where('name', $name)->exists()) {
            $this->skippedDuplicate++;
            $this->line("  Skipped duplicate: {$name}");

            return;
        }

        // Resolve coordinates: explicit lat/lng win; otherwise geocode the address.
        [$latitude, $longitude, $label] = $this->resolveLocation($row, $geocode, $dryRun);

        // Text screen over name + description + menu item names.
        $menuRows = $this->parseMenu($row['menu'] ?? '');
        $menuNames = array_map(fn (array $m): string => (string) $m['name'], $menuRows);
        $flags = $screenText($name, $row['description'] ?? '', ...$menuNames);
        $isFlagged = $flags !== [];

        if ($dryRun) {
            $action = $isFlagged ? 'HOLD (flagged)' : 'CREATE';
            $pin = $latitude !== null ? 'pinned' : 'unpinned';
            $this->line("  [dry-run] {$action}: {$name} ({$pin})");
            if ($isFlagged) {
                $this->held++;
            } else {
                $this->created++;
            }

            return;
        }

        DB::transaction(function () use ($row, $name, $latitude, $longitude, $label, $menuRows, $isFlagged, $flags, $screenText, $storeImage, $csvDir): void {
            $truck = FoodTruck::create([
                'user_id' => null,
                'name' => $name,
                'description' => ($row['description'] ?? '') !== '' ? $row['description'] : null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_label' => $label,
                'located_at' => $latitude !== null ? now() : null,
                'timezone' => $this->resolveTimezone($row['timezone'] ?? ''),
                'is_published' => ! $isFlagged,
                'screen_status' => $isFlagged ? FoodTruck::SCREEN_FLAGGED : FoodTruck::SCREEN_PASSED,
                'moderation_reason' => $isFlagged ? Str::limit('Import — flagged text: '.implode(', ', $flags), 250, '') : null,
                'reviewed_at' => null,
            ]);

            $this->syncTags($truck, $row['tags'] ?? '', $screenText);
            $this->createMenu($truck, $menuRows);
            $this->createSocialLinks($truck, $row['social_urls'] ?? '');
            $this->attachImages($truck, $row['images'] ?? '', $csvDir, $storeImage);
        });

        if ($isFlagged) {
            $this->held++;
            $this->warn("  Held for review (flagged text): {$name}");
        } else {
            $this->created++;
            $this->info("  Created: {$name}");
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: float|null, 1: float|null, 2: string|null} [lat, lng, label]
     */
    private function resolveLocation(array $row, GeocodeSearch $geocode, bool $dryRun): array
    {
        $rawLat = $row['latitude'] ?? '';
        $rawLng = $row['longitude'] ?? '';

        if ($rawLat !== '' && $rawLng !== '') {
            $lat = filter_var($rawLat, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($rawLng, FILTER_VALIDATE_FLOAT);

            if ($lat !== false && $lng !== false && abs($lat) <= 90 && abs($lng) <= 180) {
                $label = ($row['address'] ?? '') !== '' ? mb_substr($row['address'], 0, 255) : null;

                return [$lat, $lng, $label];
            }

            $this->warn('  Ignoring out-of-range coordinates for '.($row['name'] ?? 'a row').'.');
        }

        $address = $row['address'] ?? '';
        if ($address === '' || $dryRun) {
            return [null, null, null];
        }

        $hit = $geocode($address);

        // Honour Nominatim's ≤1 req/sec policy. Faked in tests via Sleep::fake().
        Sleep::for(1)->second();

        if ($hit === null) {
            $this->geocodeMisses++;
            $this->warn("  Could not geocode \"{$address}\" — importing unpinned.");

            return [null, null, null];
        }

        return [$hit['lat'], $hit['lng'], $hit['label']];
    }

    private function resolveTimezone(string $timezone): string
    {
        $timezone = trim($timezone);

        if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        /** @var string $appTimezone */
        $appTimezone = config('app.timezone', 'UTC');

        return $appTimezone;
    }

    /**
     * @param  array<int, array{name: string, price: string}>  $menuRows
     */
    private function createMenu(FoodTruck $truck, array $menuRows): void
    {
        if ($menuRows === []) {
            return;
        }

        $rows = [];
        foreach ($menuRows as $order => $item) {
            $rows[] = [
                'name' => $item['name'],
                'description' => null,
                'price_cents' => $item['price'] === '' ? null : (int) round((float) $item['price'] * 100),
                'is_available' => true,
                'sort_order' => $order,
            ];
        }

        $truck->menuItems()->createMany($rows);
    }

    /**
     * Parse a "Name=price|Name2=price2|Name3" menu cell into rows.
     *
     * @return array<int, array{name: string, price: string}>
     */
    private function parseMenu(string $menu): array
    {
        $rows = [];
        foreach ($this->splitList($menu) as $entry) {
            [$itemName, $price] = array_pad(explode('=', $entry, 2), 2, '');
            $itemName = trim($itemName);

            if ($itemName === '') {
                continue;
            }

            $rows[] = ['name' => $itemName, 'price' => trim($price)];
        }

        return $rows;
    }

    private function syncTags(FoodTruck $truck, string $tags, ScreenText $screenText): void
    {
        $ids = [];
        foreach ($this->splitList($tags) as $tagName) {
            // Tags are shared public taxonomy, so a blocklisted name is denied
            // outright (mirrors TruckEditor's authoring rule) — screen the name and
            // its slug.
            if ($screenText($tagName, Str::slug($tagName)) !== []) {
                $this->warn("  Skipped blocked tag \"{$tagName}\".");

                continue;
            }

            $tag = Tag::query()->firstOrCreate(['slug' => Str::slug($tagName)], ['name' => $tagName]);
            $ids[] = $tag->id;
        }

        if ($ids !== []) {
            $truck->tags()->sync($ids);
        }
    }

    private function createSocialLinks(FoodTruck $truck, string $socialUrls): void
    {
        $rows = [];
        foreach ($this->splitList($socialUrls) as $order => $url) {
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                continue;
            }

            $rows[] = [
                'url' => $url,
                'platform' => SocialPlatform::fromUrl($url),
                'sort_order' => $order,
            ];
        }

        if ($rows !== []) {
            $truck->socialLinks()->createMany($rows);
        }
    }

    private function attachImages(FoodTruck $truck, string $images, string $csvDir, StoreTruckImage $storeImage): void
    {
        foreach ($this->splitList($images) as $imagePath) {
            $resolved = $this->isAbsolutePath($imagePath) ? $imagePath : $csvDir.'/'.$imagePath;

            if (! is_file($resolved)) {
                $this->warn('  Image not found, skipping: '.$imagePath);

                continue;
            }

            $mimeType = mime_content_type($resolved) ?: 'image/jpeg';
            $file = new UploadedFile(
                path: $resolved,
                originalName: basename($resolved),
                mimeType: $mimeType,
                test: true,
            );

            try {
                $storeImage($truck, $file);
            } catch (Throwable $e) {
                $this->warn('  Skipped image '.basename($resolved).': '.$e->getMessage());
            }
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /**
     * Split a pipe-separated cell into trimmed, non-empty values (re-indexed).
     *
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode('|', $value)),
            fn (string $v): bool => $v !== '',
        ));
    }

    private function summary(): void
    {
        $this->newLine();
        $this->table(
            ['Created', 'Held for review', 'Skipped (duplicate)', 'Geocode misses', 'Errors'],
            [[$this->created, $this->held, $this->skippedDuplicate, $this->geocodeMisses, $this->errors]],
        );
    }
}
