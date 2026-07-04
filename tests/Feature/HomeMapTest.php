<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;

uses(RefreshDatabase::class);

it('shows the map on the homepage wired to the tag filter', function (): void {
    FoodTruck::factory()->published()->located()->create();

    $this->withoutVite()
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Map of food trucks near you')
        ->assertSee('truckMap(', escape: false)
        ->assertSee('tag-filter.window', escape: false);
});

it('offers the ZIP/region fallback for visitors who decline geolocation', function (): void {
    FoodTruck::factory()->published()->located()->create();

    $this->withoutVite()
        ->get(route('home'))
        ->assertOk()
        ->assertSee('locationSearch(', escape: false)
        // Js::from JSON-escapes slashes, so match the encoded route URL.
        ->assertSee(Js::from(route('geocode'))->toHtml(), escape: false)
        ->assertSee('ZIP code or region');
});

it('marks the card lists for closest-first sorting with each pin location', function (): void {
    FoodTruck::factory()->published()->located()->create();

    $response = $this->withoutVite()->get(route('home'))->assertOk();

    // Only the filter grid opts in to distance sorting (the popular carousel
    // keeps its favourite-count order behind the radius-only filter), but both
    // lists carry the coordinates the client-side radius cap needs — one
    // data-lat per surface for this single truck.
    expect(substr_count((string) $response->getContent(), 'x-data="truckDistanceSort"'))->toBe(1)
        ->and(substr_count((string) $response->getContent(), 'x-data="truckRadiusFilter"'))->toBe(1)
        ->and(substr_count((string) $response->getContent(), 'data-lat="39.0272000"'))->toBe(2);
});

it('pins located trucks and leaves unpinned trucks off the map', function (): void {
    $pinned = FoodTruck::factory()->published()->located()->create(['name' => 'Pinned Truck']);
    $pinned->tags()->create(['name' => 'Tacos']);
    FoodTruck::factory()->published()->create(['name' => 'Unpinned Truck']);

    $view = $this->blade(
        '<x-truck-map :trucks="$trucks" />',
        ['trucks' => FoodTruck::query()->with('tags')->get()],
    );

    $view->assertSee('Pinned Truck')
        ->assertSee('39.0272', escape: false)
        ->assertSee('tacos') // slug in the pin payload drives filtering
        ->assertDontSee('Unpinned Truck');
});
