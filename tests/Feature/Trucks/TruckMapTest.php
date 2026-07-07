<?php

declare(strict_types=1);

use App\Actions\GenerateTruckMapImage;
use App\Models\FoodTruck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * These tests never render a real map: the action only touches the network when
 * a truck has coordinates AND its fingerprint file is missing, so every case
 * below either pre-places the file or omits the coordinates.
 */

it('shows the cached map when the truck has a pin', function (): void {
    Storage::fake('public');

    $truck = FoodTruck::factory()->published()->located()->create();

    $path = GenerateTruckMapImage::relativePath($truck);
    expect($path)->not->toBeNull();
    Storage::disk('public')->put((string) $path, 'fake-png-bytes');

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url((string) $path), escape: false)
        ->assertSee('truck-page__map', escape: false);
});

it('hides the map when the truck has no pinned location', function (): void {
    Storage::fake('public');

    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertDontSee('truck-page__map', escape: false)
        ->assertDontSee('openstreetmap.org', escape: false);
});

it('fingerprints the cache path from the pin so moving the truck regenerates', function (): void {
    $truck = FoodTruck::factory()->located()->make(['id' => 1]);
    $moved = FoodTruck::factory()->make(['id' => 1, 'latitude' => 39.1, 'longitude' => -94.7]);
    $unpinned = FoodTruck::factory()->make(['id' => 1]);

    $path = GenerateTruckMapImage::relativePath($truck);

    expect($path)->toStartWith('truck-maps/1/')
        ->and($path)->toEndWith('.png')
        ->and(GenerateTruckMapImage::relativePath($truck))->toBe($path)
        ->and(GenerateTruckMapImage::relativePath($moved))->not->toBe($path)
        ->and(GenerateTruckMapImage::relativePath($unpinned))->toBeNull();
});

it('deletes stale sibling maps when storing a freshly rendered one', function (): void {
    Storage::fake('public');

    $truck = FoodTruck::factory()->published()->located()->create();
    Storage::disk('public')->put("truck-maps/{$truck->id}/stale.png", 'old-map');

    $path = (new GenerateTruckMapImage)->storeRendered($truck, 'new-map-bytes');

    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertMissing("truck-maps/{$truck->id}/stale.png");
    expect(Storage::disk('public')->get($path))->toBe('new-map-bytes');
});

it('serves the cached file without regenerating when the pin is unchanged', function (): void {
    Storage::fake('public');

    $truck = FoodTruck::factory()->located()->create();
    $path = (string) GenerateTruckMapImage::relativePath($truck);
    Storage::disk('public')->put($path, 'cached-bytes');

    // A render attempt would hit the network and fail loudly in CI; getting the
    // original bytes back proves the cache short-circuits.
    expect((new GenerateTruckMapImage)($truck))->toBe($path)
        ->and(Storage::disk('public')->get($path))->toBe('cached-bytes');
});
