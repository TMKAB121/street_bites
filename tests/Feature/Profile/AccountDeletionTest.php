<?php

declare(strict_types=1);

use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes the account and every truck the user owns, then redirects home', function (): void {
    $user = User::factory()->create();
    $mine = FoodTruck::factory()->for($user)->create();
    $theirs = FoodTruck::factory()->create();

    $this->actingAs($user)
        ->post(route('account.destroy'))
        ->assertRedirect(route('home'));

    expect(auth()->check())->toBeFalse();
    expect(User::find($user->id))->toBeNull();
    // The food_trucks FK is cascadeOnDelete — the owner's truck is gone for good
    // (bypassing the SoftDeletes scope), while another user's truck is untouched.
    expect(FoodTruck::withTrashed()->find($mine->id))->toBeNull();
    expect(FoodTruck::withTrashed()->find($theirs->id))->not->toBeNull();
});

it('rejects account deletion from a guest', function (): void {
    $this->post(route('account.destroy'))
        ->assertRedirect(route('auth.login'));
});

it('exposes a delete-account control on the profile page', function (): void {
    $user = User::factory()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Delete account')
        ->assertSee(route('account.destroy'));
});
