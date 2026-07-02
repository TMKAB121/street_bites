<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('marks the card lists for closest-first sorting with each pin location', function (): void {
    FoodTruck::factory()->published()->located()->create();

    $response = $this->withoutVite()->get(route('home'))->assertOk();

    // Both the popular carousel and the filter grid opt in, and the cards
    // carry the coordinates the client-side sort needs.
    expect(substr_count($response->getContent(), 'x-data="truckDistanceSort"'))->toBe(2)
        ->and(substr_count($response->getContent(), 'data-lat="39.0272000"'))->toBe(2);
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
