<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The star rendered on discovery cards (home) and the truck detail page:
// filled (fav-toggle--active) when the signed-in user has favourited the
// truck, hollow when not, and absent entirely for guests. Filled vs hollow is
// asserted on the server-rendered class attribute — the Alpine :class binding
// always contains the literal 'fav-toggle--active', so it can't distinguish.

it('shows a filled star on the home card for a favourited truck', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->published()->create();
    $user->favorites()->attach($truck);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('home'))
        ->assertSee('class="fav-toggle fav-toggle--active', escape: false)
        // The button targets the toggle endpoint (Js::from escapes slashes).
        ->assertSee(str_replace('/', '\/', route('favorites.toggle', $truck)), escape: false);
});

it('shows a hollow star on the home card for an unfavourited truck', function (): void {
    $user = User::factory()->create();
    FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('home'))
        ->assertSee('class="fav-toggle', escape: false)
        ->assertDontSee('class="fav-toggle fav-toggle--active', escape: false);
});

it('shows no star to guests on the home page', function (): void {
    FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('fav-toggle');
});

it('shows a filled star on the detail page for a favourited truck', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->published()->create();
    $user->favorites()->attach($truck);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('class="fav-toggle fav-toggle--active', escape: false);
});

it('shows a hollow star on the detail page for an unfavourited truck', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('class="fav-toggle', escape: false)
        ->assertDontSee('class="fav-toggle fav-toggle--active', escape: false);
});

it('shows no star to guests on the detail page', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertDontSee('fav-toggle');
});
