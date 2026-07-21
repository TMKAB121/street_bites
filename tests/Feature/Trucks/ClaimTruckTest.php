<?php

declare(strict_types=1);

use App\Mail\TruckClaimSubmitted;
use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\TruckClaimRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['admin.emails' => ['admin@example.com']]);
});

it('shows the unclaimed disclaimer and sign-in CTA to a guest', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('isn’t managed by the owner yet', false)
        ->assertSee('Sign in to claim this truck');
});

it('shows the claim form to a signed-in visitor on an unclaimed truck', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();

    $this->withoutVite()
        ->actingAs(User::factory()->create())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('Claim this truck');
});

it('shows nothing claim-related on an owned truck', function (): void {
    $truck = FoodTruck::factory()->for(User::factory())->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertDontSee('Claim this truck')
        ->assertDontSee('isn’t managed by the owner yet', false);
});

it('keeps the report control visible to a guest on an unclaimed truck', function (): void {
    // Regression: the report gate must not treat a guest (null id) as the owner
    // of an unclaimed truck (null user_id).
    $truck = FoodTruck::factory()->unclaimed()->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('Report '.$truck->name);
});

it('records a pending claim and notifies admins', function (): void {
    Mail::fake();
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('trucks.claim', $truck), ['message' => 'I run this truck — hi@example.com'])
        ->assertRedirect(route('trucks.show', [$truck, $truck->slug]));

    $claim = TruckClaimRequest::query()->sole();
    expect($claim->user_id)->toBe($user->id)
        ->and($claim->food_truck_id)->toBe($truck->id)
        ->and($claim->status)->toBe(TruckClaimRequest::STATUS_PENDING)
        ->and($claim->message)->toBe('I run this truck — hi@example.com');

    Mail::assertQueued(TruckClaimSubmitted::class);
});

it('does not email when no admins are configured', function (): void {
    config(['admin.emails' => []]);
    Mail::fake();
    $truck = FoodTruck::factory()->unclaimed()->published()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('trucks.claim', $truck));

    expect(TruckClaimRequest::query()->count())->toBe(1);
    Mail::assertNothingQueued();
});

it('treats a repeat pending claim from the same user as a no-op', function (): void {
    Mail::fake();
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('trucks.claim', $truck));
    $this->actingAs($user)->post(route('trucks.claim', $truck));

    expect($truck->claimRequests()->count())->toBe(1);
    Mail::assertQueuedCount(1);
});

it('lets a different user file their own pending claim', function (): void {
    Mail::fake();
    $truck = FoodTruck::factory()->unclaimed()->published()->create();

    $this->actingAs(User::factory()->create())->post(route('trucks.claim', $truck));
    $this->actingAs(User::factory()->create())->post(route('trucks.claim', $truck));

    expect($truck->claimRequests()->count())->toBe(2);
});

it('allows a fresh claim after a previous one was dismissed', function (): void {
    Mail::fake();
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $user = User::factory()->create();
    $truck->claimRequests()->create([
        'user_id' => $user->id,
        'status' => TruckClaimRequest::STATUS_DISMISSED,
    ]);

    $this->actingAs($user)->post(route('trucks.claim', $truck));

    expect($truck->claimRequests()->where('status', TruckClaimRequest::STATUS_PENDING)->count())->toBe(1);
});

it('404s claiming an already-owned truck', function (): void {
    $truck = FoodTruck::factory()->for(User::factory())->published()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('trucks.claim', $truck))
        ->assertNotFound();

    expect(TruckClaimRequest::query()->count())->toBe(0);
});

it('404s claiming an unpublished truck', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->create(['is_published' => false]);

    $this->actingAs(User::factory()->create())
        ->post(route('trucks.claim', $truck))
        ->assertNotFound();
});

it('does not record a claim from a banned user', function (): void {
    Mail::fake();
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $banned = User::factory()->create();
    $banned->forceFill(['banned_at' => now(), 'ban_reason' => 'x'])->save();

    $this->actingAs($banned)->post(route('trucks.claim', $truck));

    expect(TruckClaimRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('redirects a guest claim attempt to sign in', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();

    $this->post(route('trucks.claim', $truck))
        ->assertRedirect(route('auth.login'));

    expect(TruckClaimRequest::query()->count())->toBe(0);
});

it('shows the under-review state to a claimant with a pending claim', function (): void {
    $truck = FoodTruck::factory()->unclaimed()->published()->create();
    $user = User::factory()->create();
    $truck->claimRequests()->create([
        'user_id' => $user->id,
        'status' => TruckClaimRequest::STATUS_PENDING,
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('Claim submitted')
        ->assertDontSee('Claim this truck');
});
