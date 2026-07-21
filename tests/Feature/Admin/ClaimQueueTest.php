<?php

declare(strict_types=1);

use App\Livewire\Admin\ModerationQueue;
use App\Mail\TruckClaimApproved;
use App\Models\FoodTruck;
use App\Models\TruckClaimRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['admin.emails' => ['admin@example.com']]);
    Mail::fake();
});

function claimAdmin(): User
{
    return User::factory()->create(['email' => 'admin@example.com']);
}

function pendingClaim(FoodTruck $truck, ?User $user = null): TruckClaimRequest
{
    return $truck->claimRequests()->create([
        'user_id' => ($user ?? User::factory()->create())->id,
        'status' => TruckClaimRequest::STATUS_PENDING,
    ]);
}

it('lists pending claims with a badge on the claims tab', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create(['name' => 'Taco Wagon']);
    $claimant = User::factory()->create(['email' => 'claimant@example.com']);
    pendingClaim($truck, $claimant);

    Livewire::actingAs(claimAdmin())
        ->test(ModerationQueue::class)
        ->assertViewHas('pendingClaims', 1)
        ->call('setFilter', 'claims')
        ->assertSee('Taco Wagon')
        ->assertSee('claimant@example.com');
});

it('accepts the claims filter and rejects garbage', function (): void {
    Livewire::actingAs(claimAdmin())
        ->test(ModerationQueue::class)
        ->call('setFilter', 'claims')
        ->assertSet('filter', 'claims')
        ->call('setFilter', 'nonsense')
        ->assertSet('filter', 'review');
});

it('approves a claim, transferring ownership and emailing the claimant', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $claimant = User::factory()->create();
    $claim = pendingClaim($truck, $claimant);

    Livewire::actingAs(claimAdmin())
        ->test(ModerationQueue::class)
        ->call('approveClaim', $claim->id);

    expect($truck->fresh()->user_id)->toBe($claimant->id)
        ->and($truck->fresh()->is_published)->toBeTrue()
        ->and($claim->fresh()->status)->toBe(TruckClaimRequest::STATUS_APPROVED)
        ->and($claim->fresh()->reviewed_at)->not->toBeNull();

    Mail::assertQueued(TruckClaimApproved::class);
});

it('dismisses other pending claims on the same truck when one is approved', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $winner = pendingClaim($truck);
    $loser = pendingClaim($truck);

    Livewire::actingAs(claimAdmin())
        ->test(ModerationQueue::class)
        ->call('approveClaim', $winner->id);

    expect($loser->fresh()->status)->toBe(TruckClaimRequest::STATUS_DISMISSED);
});

it('closes a claim whose truck already gained an owner', function (): void {
    $truck = FoodTruck::factory()->for(User::factory())->published()->create();
    $claim = pendingClaim($truck);

    Livewire::actingAs(claimAdmin())
        ->test(ModerationQueue::class)
        ->call('approveClaim', $claim->id);

    expect($claim->fresh()->status)->toBe(TruckClaimRequest::STATUS_DISMISSED);
});

it('leaves a claim pending when the claimant is banned', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $banned = User::factory()->create();
    $banned->forceFill(['banned_at' => now(), 'ban_reason' => 'x'])->save();
    $claim = pendingClaim($truck, $banned);

    Livewire::actingAs(claimAdmin())
        ->test(ModerationQueue::class)
        ->call('approveClaim', $claim->id);

    expect($claim->fresh()->status)->toBe(TruckClaimRequest::STATUS_PENDING)
        ->and($truck->fresh()->user_id)->toBeNull();
});

it('dismisses a claim without transferring ownership', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $claim = pendingClaim($truck);

    Livewire::actingAs(claimAdmin())
        ->test(ModerationQueue::class)
        ->call('dismissClaim', $claim->id);

    expect($claim->fresh()->status)->toBe(TruckClaimRequest::STATUS_DISMISSED)
        ->and($truck->fresh()->user_id)->toBeNull();
});

it('forbids a non-admin from the moderation component (guarding claim actions)', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $claim = pendingClaim($truck);

    // mount() re-checks admin, so a non-admin is forbidden before any action runs.
    Livewire::actingAs(User::factory()->create(['email' => 'eater@example.com']))
        ->test(ModerationQueue::class)
        ->assertForbidden();

    expect($claim->fresh()->status)->toBe(TruckClaimRequest::STATUS_PENDING);
});
