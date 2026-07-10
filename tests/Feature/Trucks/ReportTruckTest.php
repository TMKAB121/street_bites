<?php

declare(strict_types=1);

use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\TruckReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the report control on the truck detail page to a guest', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->withoutVite()
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertSee('Report '.$truck->name);
});

it('hides the report control from the truck owner', function (): void {
    $owner = User::factory()->create();
    $truck = FoodTruck::factory()->for($owner)->published()->create();

    $this->withoutVite()
        ->actingAs($owner)
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        ->assertDontSee('Report '.$truck->name);
});

it('lets a guest report a truck without taking it down', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->postJson(route('trucks.report', $truck))
        ->assertOk()
        ->assertJson(['reported' => true]);

    expect(TruckReport::query()->count())->toBe(1)
        ->and($truck->fresh()->is_published)->toBeTrue();

    $report = TruckReport::query()->sole();
    expect($report->user_id)->toBeNull()
        ->and($report->reporter_hash)->not->toBeNull()
        ->and($report->status)->toBe(TruckReport::STATUS_OPEN);
});

it('dedupes repeat reports from the same guest IP', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->postJson(route('trucks.report', $truck))->assertOk();
    $this->postJson(route('trucks.report', $truck))->assertOk();

    expect($truck->reports()->count())->toBe(1);
});

it('records the reporter when signed in', function (): void {
    $truck = FoodTruck::factory()->published()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('trucks.report', $truck))
        ->assertOk();

    expect($truck->reports()->sole()->user_id)->toBe($user->id);
});

it('404s reporting an unpublished truck', function (): void {
    $truck = FoodTruck::factory()->create(['is_published' => false]);

    $this->postJson(route('trucks.report', $truck))->assertNotFound();

    expect(TruckReport::query()->count())->toBe(0);
});

it('renders the report control already done for a signed-in user who reported', function (): void {
    $truck = FoodTruck::factory()->published()->create();
    $user = User::factory()->create();
    $truck->reports()->create(['user_id' => $user->id, 'status' => TruckReport::STATUS_OPEN]);

    $this->withoutVite()
        ->actingAs($user)
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('trucks.show', [$truck, $truck->slug]))
        ->assertOk()
        // The Alpine seed for the reported state is rendered true.
        ->assertSee('reportToggle', false);
});
