<?php

declare(strict_types=1);

use App\Enums\SocialPlatform;
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
        ->get(route('trucks.show', [$truck, $truck->slug]))
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
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('No hours posted for today')
        ->assertSee('Menu coming soon.');
});

it('links a pinned truck to Google Maps directions from the visitor\'s location', function (): void {
    $truck = FoodTruck::factory()->published()->located()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee(
            'https://www.google.com/maps/dir/?api=1&amp;destination=39.0272000,-94.6558000',
            escape: false,
        )
        ->assertSee('Get directions');
});

it('hides the directions CTA when a truck has no pin', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertDontSee('Get directions');
});

it('shows a social icon link per profile, labelled by its detected platform', function (): void {
    $truck = FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);
    $truck->socialLinks()->create([
        'url' => 'https://www.instagram.com/tacotitan',
        'platform' => SocialPlatform::Instagram,
        'sort_order' => 0,
    ]);
    $truck->socialLinks()->create([
        'url' => 'https://www.snapchat.com/add/tacotitan',
        'platform' => SocialPlatform::Snapchat,
        'sort_order' => 1,
    ]);

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('Follow Taco Titan')
        ->assertSee('https://www.instagram.com/tacotitan')
        ->assertSee('aria-label="Instagram"', escape: false)
        ->assertSee('aria-label="Snapchat"', escape: false);
});

it('hides the social section when a truck has no links', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertDontSee('Follow ');
});

it('shows "open now — since" when only the opening time is set', function (): void {
    $truck = FoodTruck::factory()->published()->create();
    $truck->operatingHours()->create([
        'business_date' => today(),
        'opens_at' => '10:30',
        'closes_at' => null,
    ]);

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('Open now')
        ->assertSee('since 10:30 AM')
        ->assertDontSee('No hours posted');
});

it('returns 404 for an unpublished truck', function (): void {
    $truck = FoodTruck::factory()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertNotFound();
});

it('returns 404 for a truck that does not exist', function (): void {
    $this->withoutVite()
        ->get('/trucks/999/some-truck')
        ->assertNotFound();
});

it('builds the URL from the id plus the lowercase hyphenated name', function (): void {
    $truck = FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);

    expect(route('trucks.show', [$truck, $truck->slug], absolute: false))
        ->toBe("/trucks/{$truck->id}/taco-titan");

    $this->withoutVite()
        ->get("/trucks/{$truck->id}/taco-titan")
        ->assertOk();
});

it('returns 404 when the slug does not match the truck name', function (): void {
    $truck = FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);

    $this->withoutVite()
        ->get("/trucks/{$truck->id}/burger-barn")
        ->assertNotFound();
});

it('returns 404 without a slug segment', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get("/trucks/{$truck->id}")
        ->assertNotFound();
});

it('links every home-page card to its truck page', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('home'))
        ->assertSee(route('trucks.show', [$truck, $truck->slug]));
});
