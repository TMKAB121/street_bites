<?php

declare(strict_types=1);

use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Profile\TruckEditor;
use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['admin.emails' => ['admin@example.com']]);
});

function unclaimedAdmin(): User
{
    return User::factory()->create(['email' => 'admin@example.com']);
}

it('approves a held unclaimed truck without a null-owner crash', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->create([
        'is_published' => false,
        'screen_status' => FoodTruck::SCREEN_FLAGGED,
        'moderation_reason' => 'Import — flagged text',
    ]);

    Livewire::actingAs(unclaimedAdmin())
        ->test(ModerationQueue::class)
        ->call('approve', $truck->id);

    expect($truck->fresh()->is_published)->toBeTrue()
        ->and($truck->fresh()->screen_status)->toBe(FoodTruck::SCREEN_PASSED);
});

it('blocks nobody when block-vendor is called on an unclaimed truck', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->create(['is_published' => false]);

    Livewire::actingAs(unclaimedAdmin())
        ->test(ModerationQueue::class)
        ->call('blockOwner', $truck->id)
        ->assertDispatched('toast');

    // No user exists to ban, and the truck is untouched.
    expect(User::query()->whereNotNull('banned_at')->count())->toBe(0);
});

it('renders the review queue with an unclaimed truck present', function (): void {
    FoodTruck::factory()->unclaimed()->create([
        'is_published' => false,
        'name' => 'System Imported Truck',
    ]);

    Livewire::actingAs(unclaimedAdmin())
        ->test(ModerationQueue::class)
        ->assertOk()
        ->assertSee('System Imported Truck')
        ->assertSee('Unclaimed listing');
});

it('404s the admin block POST route on an unclaimed truck', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();

    $this->actingAs(unclaimedAdmin())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->post(route('admin.trucks.block', $truck))
        ->assertNotFound();
});

it('lets an admin edit an unclaimed truck via the editor', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->create();

    Livewire::actingAs(unclaimedAdmin())
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Cleaned Up Name')
        ->call('save');

    expect($truck->fresh()->name)->toBe('Cleaned Up Name');
});

it('forbids an admin from editing someone else’s owned truck via the editor', function (): void {
    $truck = FoodTruck::factory()->for(User::factory())->create();

    Livewire::actingAs(unclaimedAdmin())
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->assertForbidden();
});

it('forbids a non-admin from editing an unclaimed truck via the editor', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->create();

    Livewire::actingAs(User::factory()->create(['email' => 'eater@example.com']))
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->assertForbidden();
});

it('serves the admin edit page for an unclaimed truck', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->create();

    $this->withoutVite()
        ->actingAs(unclaimedAdmin())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('admin.trucks.edit', $truck))
        ->assertOk()
        ->assertSee('Edit unclaimed truck');
});

it('404s the admin edit page for an owned truck', function (): void {
    $truck = FoodTruck::factory()->for(User::factory())->create();

    $this->actingAs(unclaimedAdmin())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('admin.trucks.edit', $truck))
        ->assertNotFound();
});
