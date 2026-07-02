<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a published truck with its tags, hours, location, and menu', function (): void {
    $truck = FoodTruck::factory()->published()->create([
        'name' => 'Taco Titan',
        'description' => 'Slow-roasted al pastor from a vertical spit.',
        'location_label' => '5th & Main',
    ]);
    $truck->tags()->create(['name' => 'Tacos']);
    $truck->operatingHours()->create([
        'business_date' => today(),
        'opens_at' => '11:00',
        'closes_at' => '14:30',
    ]);
    $truck->menuItems()->create([
        'name' => 'Al Pastor Taco',
        'description' => 'Pineapple, onion, cilantro.',
        'price_cents' => 450,
        'is_available' => true,
        'sort_order' => 1,
    ]);
    $truck->menuItems()->create([
        'name' => 'Horchata',
        'price_cents' => 300,
        'is_available' => false,
        'sort_order' => 2,
    ]);

    $this->withoutVite()
        ->get(route('trucks.show', $truck))
        ->assertOk()
        ->assertSee('Taco Titan')
        ->assertSee('Slow-roasted al pastor from a vertical spit.')
        ->assertSee('Tacos')
        ->assertSee('5th &amp; Main', escape: false)
        ->assertSee('11:00 AM')
        ->assertSee('2:30 PM')
        ->assertSee('Al Pastor Taco')
        ->assertSee('Pineapple, onion, cilantro.')
        ->assertSee('$4.50')
        ->assertSee('Horchata')
        ->assertSee('Sold out');
});

it('falls back gracefully when a truck has no hours or menu yet', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', $truck))
        ->assertOk()
        ->assertSee('No hours posted for today')
        ->assertSee('Menu coming soon.');
});

it('returns 404 for an unpublished truck', function (): void {
    $truck = FoodTruck::factory()->create();

    $this->withoutVite()
        ->get(route('trucks.show', $truck))
        ->assertNotFound();
});

it('returns 404 for a truck that does not exist', function (): void {
    $this->withoutVite()
        ->get('/trucks/999')
        ->assertNotFound();
});

it('links every home-page card to its truck page', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('home'))
        ->assertSee(route('trucks.show', $truck));
});
