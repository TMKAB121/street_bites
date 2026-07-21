<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake(config('filesystems.public_disk'));
    Sleep::fake();
    // Deterministic screening — one fixture term, no overlap with real data.
    config(['moderation.text_blocklist' => ['blockedword']]);
});

/**
 * Write a CSV to a fresh temp dir and return its path. Header + rows are arrays.
 *
 * @param  list<string>  $header
 * @param  list<list<string>>  $rows
 */
function writeCsv(array $header, array $rows): string
{
    $dir = sys_get_temp_dir().'/trucks-import-'.uniqid();
    mkdir($dir);
    $path = $dir.'/trucks.csv';

    $handle = fopen($path, 'w');
    fputcsv($handle, $header);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $path;
}

it('imports an unclaimed published truck with tags, menu, social, and pin', function (): void {
    $path = writeCsv(
        ['name', 'description', 'latitude', 'longitude', 'tags', 'menu', 'social_urls', 'timezone'],
        [[
            'Taco Wagon',
            'Best tacos in town',
            '39.0272',
            '-94.6558',
            'Mexican|Tacos',
            'Al Pastor Taco=4.50|Chips',
            'https://instagram.com/tacowagon',
            'America/Chicago',
        ]],
    );

    $this->artisan('trucks:import', ['path' => $path])->assertSuccessful();

    $truck = FoodTruck::query()->sole();
    expect($truck->user_id)->toBeNull()
        ->and($truck->is_published)->toBeTrue()
        ->and((float) $truck->latitude)->toBe(39.0272)
        ->and($truck->timezone)->toBe('America/Chicago')
        ->and($truck->tags()->count())->toBe(2)
        ->and($truck->menuItems()->count())->toBe(2)
        ->and($truck->socialLinks()->count())->toBe(1);

    $taco = $truck->menuItems()->where('name', 'Al Pastor Taco')->sole();
    expect($taco->price_cents)->toBe(450);

    Http::assertNothingSent();
});

it('geocodes an address-only row', function (): void {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => '39.05',
            'lon' => '-94.60',
            'display_name' => 'Mission, KS',
        ]]),
    ]);

    $path = writeCsv(['name', 'address'], [['Curbside Kitchen', '66202']]);

    $this->artisan('trucks:import', ['path' => $path])->assertSuccessful();

    $truck = FoodTruck::query()->sole();
    expect((float) $truck->latitude)->toBe(39.05)
        ->and($truck->location_label)->toBe('Mission, KS');

    Http::assertSentCount(1);
});

it('imports a truck unpinned when geocoding misses', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

    $path = writeCsv(['name', 'address'], [['Nowhere Eats', 'not a real place at all']]);

    $this->artisan('trucks:import', ['path' => $path])->assertSuccessful();

    $truck = FoodTruck::query()->sole();
    expect($truck->latitude)->toBeNull()
        ->and($truck->is_published)->toBeTrue();
});

it('skips a duplicate name, including soft-deleted trucks', function (): void {
    FoodTruck::factory()->create(['name' => 'Taco Wagon']);
    $removed = FoodTruck::factory()->create(['name' => 'Ghost Truck']);
    $removed->delete();

    $path = writeCsv(['name'], [['Taco Wagon'], ['Ghost Truck'], ['Fresh Truck']]);

    $this->artisan('trucks:import', ['path' => $path])->assertSuccessful();

    // Only the genuinely new name is created.
    expect(FoodTruck::query()->where('name', 'Fresh Truck')->count())->toBe(1)
        ->and(FoodTruck::withTrashed()->count())->toBe(3);
});

it('holds a truck with blocklisted text for review', function (): void {
    $path = writeCsv(['name', 'description'], [['Clean Name', 'this has a blockedword in it']]);

    $this->artisan('trucks:import', ['path' => $path])->assertSuccessful();

    $truck = FoodTruck::query()->sole();
    expect($truck->is_published)->toBeFalse()
        ->and($truck->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED)
        ->and($truck->moderation_reason)->toContain('blockedword')
        ->and($truck->reviewed_at)->toBeNull();
});

it('skips a blocklisted tag but still imports the truck', function (): void {
    $path = writeCsv(['name', 'tags'], [['Good Truck', 'Mexican|blockedword']]);

    $this->artisan('trucks:import', ['path' => $path])->assertSuccessful();

    $truck = FoodTruck::query()->sole();
    expect($truck->tags()->count())->toBe(1)
        ->and($truck->tags()->first()->name)->toBe('Mexican');
});

it('stores an image referenced relative to the CSV directory', function (): void {
    $path = writeCsv(['name', 'images'], [['Photo Truck', 'truck.jpg']]);
    copy(
        base_path('database/seeders/fixtures/images/test1.jpg'),
        dirname($path).'/truck.jpg',
    );

    $this->artisan('trucks:import', ['path' => $path])->assertSuccessful();

    $truck = FoodTruck::query()->sole();
    expect($truck->images()->count())->toBe(1);
    Storage::disk(config('filesystems.public_disk'))->assertExists($truck->images()->first()->path);
});

it('warns and still creates the truck when an image file is missing', function (): void {
    $path = writeCsv(['name', 'images'], [['No Photo Truck', 'does-not-exist.jpg']]);

    $this->artisan('trucks:import', ['path' => $path])->assertSuccessful();

    $truck = FoodTruck::query()->sole();
    expect($truck->images()->count())->toBe(0);
});

it('writes nothing and sends no requests on a dry run', function (): void {
    Http::fake();
    $path = writeCsv(['name', 'address'], [['Dry Run Truck', '66202']]);

    $this->artisan('trucks:import', ['path' => $path, '--dry-run' => true])->assertSuccessful();

    expect(FoodTruck::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('fails on a missing file', function (): void {
    $this->artisan('trucks:import', ['path' => '/no/such/file.csv'])->assertFailed();
});

it('fails when the CSV lacks a name column', function (): void {
    $path = writeCsv(['description'], [['just a description']]);

    $this->artisan('trucks:import', ['path' => $path])->assertFailed();
});
