<?php

declare(strict_types=1);

use App\Models\CookieConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('signs the user out and redirects home', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    expect(auth()->check())->toBeFalse();
});

it('rejects a logout from a guest', function (): void {
    // No authenticated user → the auth middleware redirects to sign in.
    $this->post(route('logout'))
        ->assertRedirect(route('auth.login'));
});

it('exposes a sign-out control on the profile page', function (): void {
    $user = User::factory()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Sign out')
        ->assertSee(route('logout'));
});

it('shows a sign-out control in the header menu when signed in', function (): void {
    $user = User::factory()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Sign out')
        ->assertSee(route('logout'));
});

it('hides the header sign-out control from guests', function (): void {
    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertDontSee('Sign out')
        ->assertDontSee(route('logout'));
});
