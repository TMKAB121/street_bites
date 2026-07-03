<?php

declare(strict_types=1);

use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests to the sign-in page', function (): void {
    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('favorites'))
        ->assertRedirect(route('auth.login'));
});

it('requires cookie consent', function (): void {
    $user = User::factory()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('favorites'))
        ->assertRedirect(route('home'));
});

it('shows only the user\'s favourited published trucks', function (): void {
    $user = User::factory()->create();
    $favorited = FoodTruck::factory()->published()->create(['name' => 'Falafel Express']);
    $unfavorited = FoodTruck::factory()->published()->create(['name' => 'Waffle Wagon']);
    $unpublished = FoodTruck::factory()->create(['name' => 'Ghost Kitchen']);
    $user->favorites()->attach([$favorited->id, $unpublished->id]);

    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->actingAs($user)
        ->get(route('favorites'))
        ->assertOk()
        ->assertSee('Your favorite trucks')
        ->assertSee('Falafel Express')
        ->assertDontSee('Waffle Wagon')
        ->assertDontSee('Ghost Kitchen');
});

it('renders every favourite with a filled star', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->published()->create();
    $user->favorites()->attach($truck);

    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->actingAs($user)
        ->get(route('favorites'))
        ->assertSee('class="fav-toggle fav-toggle--active', escape: false);
});

it('only offers filter pills for cuisines among the favourites', function (): void {
    $user = User::factory()->create();
    $favorited = FoodTruck::factory()->published()->create();
    $other = FoodTruck::factory()->published()->create();
    $tacos = Tag::query()->create(['name' => 'Tacos']);
    $waffles = Tag::query()->create(['name' => 'Waffles']);
    $favorited->tags()->attach($tacos);
    $other->tags()->attach($waffles);
    $user->favorites()->attach($favorited);

    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->actingAs($user)
        ->get(route('favorites'))
        ->assertSee('Tacos')
        ->assertDontSee('Waffles');
});

it('shows an empty state when nothing is favourited', function (): void {
    $user = User::factory()->create();

    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->actingAs($user)
        ->get(route('favorites'))
        ->assertOk()
        ->assertSee('No favorites yet');
});

it('points the nav favorites tab at the favorites route', function (): void {
    $this->withoutVite()
        ->get(route('home'))
        ->assertSee(route('favorites'), escape: false);
});
