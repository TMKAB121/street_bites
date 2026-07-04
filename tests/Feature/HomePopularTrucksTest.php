<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// The home page's "Popular near you" carousel: the ten most-favourited
// published trucks, open-now first, then by favourite count. Ordering is
// asserted on the view's $popular collection — the same truck names also
// appear in the discovery grid below, so the HTML can't distinguish.

/** Attach $count distinct users as favourites of the truck. */
function favorite(FoodTruck $truck, int $count): FoodTruck
{
    $truck->favoritedBy()->attach(User::factory()->count($count)->create());

    return $truck;
}

/** @return array<int, int> the popular truck ids, in order. */
function popularIds(TestResponse $response): array
{
    return $response->viewData('popular')->pluck('id')->all();
}

it('orders the popular carousel by favourite count', function (): void {
    $bronze = favorite(FoodTruck::factory()->published()->create(), 1);
    $gold = favorite(FoodTruck::factory()->published()->create(), 3);
    $silver = favorite(FoodTruck::factory()->published()->create(), 2);

    $response = $this->withoutVite()->get(route('home'))->assertOk();

    expect(popularIds($response))->toBe([$gold->id, $silver->id, $bronze->id]);
});

it('puts open-now trucks first, then sorts by favourite count', function (): void {
    $closedPopular = favorite(FoodTruck::factory()->published()->create(), 5);
    $openQuiet = favorite(FoodTruck::factory()->published()->create(), 1);
    $openPopular = favorite(FoodTruck::factory()->published()->create(), 3);

    // An all-day window (no timezone set, so UTC wall-clock) — open right now.
    foreach ([$openQuiet, $openPopular] as $truck) {
        $truck->operatingHours()->create([
            'business_date' => today(),
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
        ]);
    }

    $response = $this->withoutVite()->get(route('home'))->assertOk();

    expect(popularIds($response))->toBe([$openPopular->id, $openQuiet->id, $closedPopular->id]);
});

it('limits the popular carousel to the ten most-favourited trucks', function (): void {
    // Eleven trucks with favourite counts 11..1 — the 1-favourite truck
    // misses the cut.
    $trucks = collect(range(11, 1))
        ->map(fn (int $count): FoodTruck => favorite(FoodTruck::factory()->published()->create(), $count));

    $response = $this->withoutVite()->get(route('home'))->assertOk();

    expect(popularIds($response))
        ->toHaveCount(10)
        ->toBe($trucks->take(10)->pluck('id')->all());
});

it('excludes unpublished trucks from the popular carousel', function (): void {
    favorite(FoodTruck::factory()->create(), 5);
    $published = favorite(FoodTruck::factory()->published()->create(), 1);

    $response = $this->withoutVite()->get(route('home'))->assertOk();

    expect(popularIds($response))->toBe([$published->id]);
});
