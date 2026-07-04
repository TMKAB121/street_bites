<?php

declare(strict_types=1);

use App\Livewire\Profile\ProfilePage;
use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('redirects guests to the sign-in page', function (): void {
    $this->withoutVite()
        ->get(route('profile'))
        ->assertRedirect(route('auth.login'));
});

it('shows the profile to a signed-in user with the add-a-truck CTA', function (): void {
    $user = User::factory()->create();

    // The profile sits behind cookie consent (RequireCookieConsent).
    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->actingAs($user)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Add a food truck');
});

it('treats a new user as an eater with no trucks', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertSee('Your food trucks')
        ->assertSet('expanded', []);
});

it('creates a truck owned by the user (and no hours yet) when the CTA is used', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->call('addTruck');

    $truck = FoodTruck::query()->sole();

    expect($truck->user_id)->toBe($user->id)
        ->and($truck->operatingHours()->count())->toBe(0);
});

it('expands the new truck so its editor loads immediately', function (): void {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->call('addTruck');

    $truck = FoodTruck::query()->sole();

    $component->assertSet('expanded', [$truck->id]);
});

it('lists the signed-in user\'s favourited trucks', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->create(['name' => 'Falafel Express']);
    $user->favorites()->attach($truck);

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertSee('Falafel Express');
});

it('renders favourites as a slim alphabetical list of links with filled stars', function (): void {
    $user = User::factory()->create();
    $zebra = FoodTruck::factory()->create(['name' => 'Zebra Cakes']);
    $arepa = FoodTruck::factory()->create(['name' => 'Arepa Avenue']);
    // Attach z-first so alphabetical ordering is doing the work, not insertion.
    $user->favorites()->attach([$zebra->id, $arepa->id]);

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertSeeInOrder(['Arepa Avenue', 'Zebra Cakes'])
        // Each row: a link to the truck page + the filled favourite star.
        ->assertSeeHtml(route('trucks.show', $arepa))
        ->assertSeeHtml('class="fav-toggle fav-toggle--active')
        // No card grid — the list replaced <x-food-truck-card>.
        ->assertDontSeeHtml('food-truck-card');
});

it('only shows the user\'s own trucks, not other vendors\'', function (): void {
    $user = User::factory()->create();
    $mine = FoodTruck::factory()->for($user)->create(['name' => 'My Truck']);
    $theirs = FoodTruck::factory()->create(['name' => 'Their Truck']);

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertSee('My Truck')
        ->assertDontSee('Their Truck');
});

it('points the signed-in nav profile link at the profile route', function (): void {
    $user = User::factory()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('home'))
        ->assertSee(route('profile'), escape: false);
});

it('removes a truck from the expanded set when its editor reports a delete', function (): void {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->call('addTruck');

    $truck = FoodTruck::query()->sole();

    $component->call('onTruckDeleted', $truck->id)
        ->assertSet('expanded', []);
});
