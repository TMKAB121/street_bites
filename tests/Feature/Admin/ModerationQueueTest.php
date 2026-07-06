<?php

declare(strict_types=1);

use App\Actions\ScreenText;
use App\Livewire\Admin\ModerationQueue;
use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\ModerationTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['admin.emails' => ['admin@example.com']]);
});

function moderationAdmin(): User
{
    return User::factory()->create(['email' => 'admin@example.com']);
}

it('forbids non-admins from the moderation route', function (): void {
    $user = User::factory()->create(['email' => 'eater@example.com']);

    $this->withoutVite()
        ->actingAs($user)
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('admin.trucks'))
        ->assertForbidden();
});

it('shows the moderation queue to an admin', function (): void {
    $this->withoutVite()
        ->actingAs(moderationAdmin())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('admin.trucks'))
        ->assertOk();
});

it('forbids a non-admin from the moderation component', function (): void {
    $user = User::factory()->create(['email' => 'eater@example.com']);

    Livewire::actingAs($user)
        ->test(ModerationQueue::class)
        ->assertForbidden();
});

it('lists unreviewed trucks in review and trashed trucks in removed', function (): void {
    FoodTruck::factory()->create(['name' => 'Pending Wagon', 'is_published' => true, 'reviewed_at' => null]);
    FoodTruck::factory()->published()->create(['name' => 'Reviewed Rig', 'reviewed_at' => now()]);
    $removed = FoodTruck::factory()->create(['name' => 'Gone Grill']);
    $removed->delete();

    Livewire::actingAs(moderationAdmin())
        ->test(ModerationQueue::class)
        ->assertSee('Pending Wagon')
        ->assertDontSee('Reviewed Rig')
        ->assertDontSee('Gone Grill')
        ->call('setFilter', 'removed')
        ->assertSee('Gone Grill')
        ->assertDontSee('Pending Wagon');
});

it('approves a held truck and publishes it', function (): void {
    $truck = FoodTruck::factory()->create([
        'is_published' => false,
        'screen_status' => FoodTruck::SCREEN_FLAGGED,
        'moderation_reason' => 'Text: porn',
        'reviewed_at' => null,
    ]);

    Livewire::actingAs(moderationAdmin())
        ->test(ModerationQueue::class)
        ->call('approve', $truck->id)
        ->assertDispatched('toast', type: 'success');

    $truck->refresh();
    expect($truck->is_published)->toBeTrue()
        ->and($truck->reviewed_at)->not->toBeNull()
        ->and($truck->screen_status)->toBe(FoodTruck::SCREEN_PASSED)
        ->and($truck->moderation_reason)->toBeNull();
});

it('clears image flags on approve so the truck is not re-held later', function (): void {
    $truck = FoodTruck::factory()->create([
        'is_published' => false,
        'screen_status' => FoodTruck::SCREEN_FLAGGED,
        'moderation_reason' => 'Flagged image',
        'reviewed_at' => null,
    ]);
    $truck->images()->create([
        'path' => 'truck-images/x.webp',
        'sort_order' => 0,
        'screen_status' => FoodTruck::SCREEN_FLAGGED,
        'flag_labels' => 'Explicit Nudity',
    ]);

    Livewire::actingAs(moderationAdmin())
        ->test(ModerationQueue::class)
        ->call('approve', $truck->id);

    $truck->refresh();
    expect($truck->is_published)->toBeTrue()
        ->and($truck->images()->where('screen_status', FoodTruck::SCREEN_FLAGGED)->count())->toBe(0);
});

it('removes a truck as a soft-delete that 404s publicly', function (): void {
    $truck = FoodTruck::factory()->published()->create(['reviewed_at' => now()]);

    Livewire::actingAs(moderationAdmin())
        ->test(ModerationQueue::class)
        ->call('remove', $truck->id);

    expect(FoodTruck::query()->find($truck->id))->toBeNull()
        ->and(FoodTruck::withTrashed()->find($truck->id))->not->toBeNull();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertNotFound();
});

it('restores a removed truck unpublished and back in the queue', function (): void {
    $truck = FoodTruck::factory()->published()->create(['reviewed_at' => now()]);
    $truck->delete();

    Livewire::actingAs(moderationAdmin())
        ->test(ModerationQueue::class)
        ->call('setFilter', 'removed')
        ->call('restore', $truck->id);

    $truck->refresh();
    expect($truck->trashed())->toBeFalse()
        ->and($truck->is_published)->toBeFalse()
        ->and($truck->reviewed_at)->toBeNull();
});

it('lets an admin add a blocked word that takes effect immediately', function (): void {
    Livewire::actingAs(moderationAdmin())
        ->test(ModerationQueue::class)
        ->set('newTerm', '  Gadzooks ')
        ->call('addTerm')
        ->assertSet('newTerm', '')
        ->assertSee('gadzooks');

    // Stored normalised, and screening picks it up on the next call (cache busted).
    expect(ModerationTerm::query()->sole()->term)->toBe('gadzooks')
        ->and(app(ScreenText::class)('gadzooks burger'))->toContain('gadzooks');
});

it('lets an admin remove a blocked word', function (): void {
    $term = ModerationTerm::create(['term' => 'gadzooks']);

    Livewire::actingAs(moderationAdmin())
        ->test(ModerationQueue::class)
        ->call('removeTerm', $term->id);

    expect(ModerationTerm::query()->count())->toBe(0)
        ->and(app(ScreenText::class)('gadzooks burger'))->toBe([]);
});

it('blocks a vendor and unpublishes all their trucks', function (): void {
    $vendor = User::factory()->create();
    $first = FoodTruck::factory()->for($vendor)->published()->create();
    $second = FoodTruck::factory()->for($vendor)->published()->create();

    Livewire::actingAs(moderationAdmin())
        ->test(ModerationQueue::class)
        ->call('blockOwner', $first->id);

    expect($vendor->fresh()->isBanned())->toBeTrue()
        ->and($first->fresh()->is_published)->toBeFalse()
        ->and($second->fresh()->is_published)->toBeFalse();
});
