<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Stamp today's operating window on a truck. Times are naive truck-local
 * wall-clock strings, matching how the editor stores them.
 */
function stampHours(FoodTruck $truck, ?string $opensAt, ?string $closesAt = null): FoodTruck
{
    $truck->operatingHours()->create([
        'business_date' => today(),
        'opens_at' => $opensAt,
        'closes_at' => $closesAt,
    ]);

    return $truck->fresh(['todayHours']);
}

it('is open when the local wall-clock is inside the posted window', function (): void {
    // 15:30 UTC == 10:30 America/Chicago (CDT), inside 09:00–17:00.
    $this->travelTo(Carbon::parse('2026-07-01 15:30:00', 'UTC'));

    $truck = FoodTruck::factory()->located()->create();

    expect(stampHours($truck, '09:00:00', '17:00:00')->isOpenNow())->toBeTrue();
});

it('is closed before opening and after closing', function (): void {
    $truck = FoodTruck::factory()->located()->create();

    // 12:30 UTC == 07:30 Chicago — before a 09:00 open.
    $this->travelTo(Carbon::parse('2026-07-01 12:30:00', 'UTC'));
    expect(stampHours($truck, '09:00:00', '17:00:00')->isOpenNow())->toBeFalse();

    // 23:30 UTC == 18:30 Chicago — after a 17:00 close.
    $this->travelTo(Carbon::parse('2026-07-01 23:30:00', 'UTC'));
    expect($truck->fresh(['todayHours'])->isOpenNow())->toBeFalse();
});

it('treats an open time with no close time as still serving', function (): void {
    $this->travelTo(Carbon::parse('2026-07-01 20:00:00', 'UTC'));

    $truck = FoodTruck::factory()->located()->create();

    expect(stampHours($truck, '09:00:00', null)->isOpenNow())->toBeTrue();
});

it('is closed with no hours posted for today', function (): void {
    $truck = FoodTruck::factory()->located()->create();

    expect($truck->fresh(['todayHours'])->isOpenNow())->toBeFalse();
});

it('orders open trucks ahead of closed ones on the home page, alphabetical within each group', function (): void {
    $this->travelTo(Carbon::parse('2026-07-01 15:30:00', 'UTC'));

    // Names deliberately out of open-order so a pure alphabetical sort would fail.
    FoodTruck::factory()->published()->located()->create(['name' => 'Alpha (closed)']);
    $zulu = FoodTruck::factory()->published()->located()->create(['name' => 'Zulu (open)']);
    $mike = FoodTruck::factory()->published()->located()->create(['name' => 'Mike (open)']);
    stampHours($zulu, '09:00:00', '17:00:00');
    stampHours($mike, '09:00:00', '17:00:00');

    $content = $this->withoutVite()->get(route('home'))->assertOk()->getContent();

    // Open trucks first (Mike then Zulu — alphabetical), then the closed Alpha.
    expect($content)->toContain('Mike (open)');
    $mikePos = strpos($content, 'Mike (open)');
    $zuluPos = strpos($content, 'Zulu (open)');
    $alphaPos = strpos($content, 'Alpha (closed)');

    expect($mikePos)->toBeLessThan($zuluPos)
        ->and($zuluPos)->toBeLessThan($alphaPos);
});

it('renders the Now Open badge on the home page only for open trucks', function (): void {
    $this->travelTo(Carbon::parse('2026-07-01 15:30:00', 'UTC'));

    $open = FoodTruck::factory()->published()->located()->create(['name' => 'Open Truck']);
    stampHours($open, '09:00:00', '17:00:00');

    FoodTruck::factory()->published()->located()->create(['name' => 'Closed Truck']);

    $content = $this->withoutVite()->get(route('home'))->assertOk()->getContent();

    // The open truck shows the badge; the badge appears once per card list
    // (carousel + grid), so twice for the single open truck.
    expect(substr_count($content, 'Now Open'))->toBe(2)
        ->and($content)->toContain('food-truck-card__status-dot');
});
