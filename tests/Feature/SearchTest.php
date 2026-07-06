<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// Header search: /search is the landing page the search bar submits to, and
// /api/search feeds its typeahead dropdown. Both match published trucks by
// name, cuisine tag, or menu item name via FoodTruck::search().

/** @return array<int, int> the result truck ids, in order. */
function searchIds(TestResponse $response): array
{
    return $response->viewData('trucks')->pluck('id')->all();
}

it('lists trucks matching by name on the search page', function (): void {
    $match = FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);
    FoodTruck::factory()->published()->create(['name' => 'Burger Barn']);

    $response = $this->withoutVite()->get(route('search', ['q' => 'taco']))->assertOk();

    expect(searchIds($response))->toBe([$match->id]);
});

it('matches trucks by cuisine tag on the search page', function (): void {
    $match = FoodTruck::factory()->published()->create(['name' => 'Patty Wagon']);
    $match->tags()->create(['name' => 'Burgers']);
    FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);

    $response = $this->withoutVite()->get(route('search', ['q' => 'Burgers']))->assertOk();

    expect(searchIds($response))->toBe([$match->id]);
});

it('matches trucks by menu item name on the search page', function (): void {
    $match = FoodTruck::factory()->published()->create(['name' => 'Patty Wagon']);
    $match->menuItems()->create(['name' => 'Smash Burger', 'sort_order' => 0]);
    FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);

    $response = $this->withoutVite()->get(route('search', ['q' => 'smash']))->assertOk();

    expect(searchIds($response))->toBe([$match->id]);
});

it('excludes unpublished trucks from search results', function (): void {
    FoodTruck::factory()->create(['name' => 'Taco Hidden']);
    $published = FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);

    $response = $this->withoutVite()->get(route('search', ['q' => 'taco']))->assertOk();

    expect(searchIds($response))->toBe([$published->id]);
});

it('shows no results for an empty query', function (): void {
    FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);

    $response = $this->withoutVite()->get(route('search'))->assertOk();

    expect(searchIds($response))->toBe([]);
});

it('treats LIKE wildcards in the query as literal characters', function (): void {
    FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);

    $response = $this->withoutVite()->get(route('search', ['q' => '%%']))->assertOk();

    expect(searchIds($response))->toBe([]);
});

it('puts open-now trucks first on the search page', function (): void {
    $closed = FoodTruck::factory()->published()->create(['name' => 'Taco Alpha']);
    $open = FoodTruck::factory()->published()->create(['name' => 'Taco Zulu']);

    // An all-day window (no timezone set, so UTC wall-clock) — open right now.
    $open->operatingHours()->create([
        'business_date' => today(),
        'opens_at' => '00:00:00',
        'closes_at' => '23:59:59',
    ]);

    $response = $this->withoutVite()->get(route('search', ['q' => 'taco']))->assertOk();

    expect(searchIds($response))->toBe([$open->id, $closed->id]);
});

it('suggests matching trucks with detail-page links', function (): void {
    $truck = FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);
    FoodTruck::factory()->published()->create(['name' => 'Burger Barn']);

    $this->getJson(route('search.suggest', ['q' => 'taco']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJson([[
            'id' => $truck->id,
            'name' => 'Taco Titan',
            'url' => route('trucks.show', [$truck, $truck->slug]),
            'context' => null,
        ]]);
});

it('explains tag and menu matches in the suggestion context', function (): void {
    $tagged = FoodTruck::factory()->published()->create(['name' => 'Patty Wagon']);
    $tagged->tags()->create(['name' => 'Burgers']);

    $this->getJson(route('search.suggest', ['q' => 'burger']))
        ->assertOk()
        ->assertJson([[
            'id' => $tagged->id,
            'context' => 'Burgers',
        ]]);
});

it('rejects suggestion queries under two characters', function (): void {
    $this->getJson(route('search.suggest', ['q' => 'a']))
        ->assertUnprocessable();
});

it('caps suggestions at eight trucks', function (): void {
    FoodTruck::factory()->published()->count(10)->sequence(
        fn ($sequence): array => ['name' => 'Taco Truck '.$sequence->index],
    )->create();

    $this->getJson(route('search.suggest', ['q' => 'taco']))
        ->assertOk()
        ->assertJsonCount(8);
});

it('excludes unpublished trucks from suggestions', function (): void {
    FoodTruck::factory()->create(['name' => 'Taco Hidden']);

    $this->getJson(route('search.suggest', ['q' => 'taco']))
        ->assertOk()
        ->assertJsonCount(0);
});
