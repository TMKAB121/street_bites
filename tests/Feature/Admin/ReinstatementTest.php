<?php

declare(strict_types=1);

use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Profile\ProfilePage;
use App\Models\FoodTruck;
use App\Models\ReinstatementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['admin.emails' => ['admin@example.com']]);
});

function reinstateAdmin(): User
{
    return User::factory()->create(['email' => 'admin@example.com']);
}

function bannedVendor(): User
{
    $vendor = User::factory()->create();
    $vendor->forceFill(['banned_at' => now(), 'ban_reason' => 'Offensive content'])->save();

    return $vendor;
}

it('lets a blocked vendor request reinstatement from their profile', function (): void {
    $vendor = bannedVendor();

    Livewire::actingAs($vendor)
        ->test(ProfilePage::class)
        ->set('reinstatementMessage', 'It was a misunderstanding.')
        ->call('requestReinstatement')
        ->assertSet('reinstatementMessage', '')
        ->assertDispatched('toast');

    $request = ReinstatementRequest::query()->sole();
    expect($request->user_id)->toBe($vendor->id)
        ->and($request->status)->toBe(ReinstatementRequest::STATUS_PENDING)
        ->and($request->message)->toBe('It was a misunderstanding.');
});

it('ignores a reinstatement request from a vendor who is not banned', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(ProfilePage::class)
        ->call('requestReinstatement');

    expect(ReinstatementRequest::query()->count())->toBe(0);
});

it('does not create a second pending request for the same vendor', function (): void {
    $vendor = bannedVendor();
    $vendor->reinstatementRequests()->create(['status' => ReinstatementRequest::STATUS_PENDING]);

    Livewire::actingAs($vendor)
        ->test(ProfilePage::class)
        ->call('requestReinstatement');

    expect(ReinstatementRequest::query()->count())->toBe(1);
});

it('lists pending requests on the moderation reinstatement tab', function (): void {
    $vendor = bannedVendor();
    $vendor->reinstatementRequests()->create([
        'status' => ReinstatementRequest::STATUS_PENDING,
        'message' => 'Please let me back',
    ]);

    Livewire::actingAs(reinstateAdmin())
        ->test(ModerationQueue::class)
        ->call('setFilter', 'reinstatement')
        ->assertSee($vendor->email)
        ->assertSee('Please let me back');
});

it('reinstates a vendor, lifting the ban but leaving their trucks unpublished', function (): void {
    $vendor = bannedVendor();
    $truck = FoodTruck::factory()->for($vendor)->create(['is_published' => false]);
    $request = $vendor->reinstatementRequests()->create(['status' => ReinstatementRequest::STATUS_PENDING]);

    Livewire::actingAs(reinstateAdmin())
        ->test(ModerationQueue::class)
        ->call('reinstate', $request->id);

    expect($vendor->fresh()->isBanned())->toBeFalse()
        ->and($vendor->fresh()->ban_reason)->toBeNull()
        ->and($request->fresh()->status)->toBe(ReinstatementRequest::STATUS_APPROVED)
        ->and($request->fresh()->reviewed_at)->not->toBeNull()
        // Their old truck stays down — they re-save to republish.
        ->and($truck->fresh()->is_published)->toBeFalse();
});

it('dismisses a request without lifting the ban', function (): void {
    $vendor = bannedVendor();
    $request = $vendor->reinstatementRequests()->create(['status' => ReinstatementRequest::STATUS_PENDING]);

    Livewire::actingAs(reinstateAdmin())
        ->test(ModerationQueue::class)
        ->call('dismissRequest', $request->id);

    expect($vendor->fresh()->isBanned())->toBeTrue()
        ->and($request->fresh()->status)->toBe(ReinstatementRequest::STATUS_DISMISSED);
});

it('forbids a non-admin from the moderation component (guarding reinstatement)', function (): void {
    Livewire::actingAs(User::factory()->create(['email' => 'eater@example.com']))
        ->test(ModerationQueue::class)
        ->assertForbidden();
});
