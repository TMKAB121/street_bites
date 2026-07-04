<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects guests', function (): void {
    $truck = FoodTruck::factory()->published()->create();

    $this->postJson(route('favorites.toggle', $truck))
        ->assertUnauthorized();
});

it('favorites a truck on first toggle', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->published()->create();

    $this->actingAs($user)
        ->postJson(route('favorites.toggle', $truck))
        ->assertOk()
        ->assertJson(['favorited' => true]);

    expect($user->favorites()->whereKey($truck->id)->exists())->toBeTrue();
});

it('unfavorites a truck on the next toggle', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->published()->create();
    $user->favorites()->attach($truck);

    $this->actingAs($user)
        ->postJson(route('favorites.toggle', $truck))
        ->assertOk()
        ->assertJson(['favorited' => false]);

    expect($user->favorites()->whereKey($truck->id)->exists())->toBeFalse();
});

it('only affects the acting user\'s favorite', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $truck = FoodTruck::factory()->published()->create();
    $other->favorites()->attach($truck);

    $this->actingAs($user)
        ->postJson(route('favorites.toggle', $truck))
        ->assertOk()
        ->assertJson(['favorited' => true]);

    expect($other->favorites()->whereKey($truck->id)->exists())->toBeTrue();
});

it('404s for unpublished trucks', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->create();

    $this->actingAs($user)
        ->postJson(route('favorites.toggle', $truck))
        ->assertNotFound();
});
