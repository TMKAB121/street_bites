<?php

declare(strict_types=1);

use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['admin.emails' => ['admin@example.com']]);
});

function detailAdmin(): User
{
    return User::factory()->create(['email' => 'admin@example.com']);
}

it('shows the moderator panel on the truck detail page only to admins', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    // Admin sees the controls.
    $this->withoutVite()
        ->actingAs(detailAdmin())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('Remove this truck')
        ->assertSee('Block this vendor');
});

it('hides the moderator panel from a signed-in non-admin and from guests', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->actingAs(User::factory()->create(['email' => 'eater@example.com']))
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertDontSee('Remove this truck')
        ->assertDontSee('Block this vendor');

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertDontSee('Remove this truck');
});

it('lets an admin remove a truck from the detail page and lands on the Removed tab', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->actingAs(detailAdmin())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->post(route('admin.trucks.remove', $truck))
        ->assertRedirect(route('admin.trucks', ['filter' => 'removed']));

    expect(FoodTruck::query()->find($truck->id))->toBeNull()
        ->and(FoodTruck::withTrashed()->find($truck->id))->not->toBeNull();
});

it('lets an admin block a vendor from the detail page and unpublishes their trucks', function (): void {
    $vendor = User::factory()->create();
    $truck = FoodTruck::factory()->for($vendor)->published()->create();
    $other = FoodTruck::factory()->for($vendor)->published()->create();

    $this->actingAs(detailAdmin())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->post(route('admin.trucks.block', $truck))
        ->assertRedirect(route('admin.trucks'));

    expect($vendor->fresh()->isBanned())->toBeTrue()
        ->and($truck->fresh()->is_published)->toBeFalse()
        ->and($other->fresh()->is_published)->toBeFalse();
});

it('forbids a non-admin from the detail-page moderator routes', function (): void {
    $truck = FoodTruck::factory()->published()->create();
    $eater = User::factory()->create(['email' => 'eater@example.com']);

    $this->actingAs($eater)
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->post(route('admin.trucks.remove', $truck))
        ->assertForbidden();

    expect(FoodTruck::query()->find($truck->id))->not->toBeNull();
});
