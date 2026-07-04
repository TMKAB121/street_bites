<?php

declare(strict_types=1);

use App\Models\CookieConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Recording consent (GDPR documentation duty) ------------------------------

it('documents an accepted choice and sets the consent cookie', function (): void {
    $this->postJson(route('cookie-consent.store'), ['status' => 'accepted'])
        ->assertOk()
        ->assertJson(['status' => 'accepted', 'signedOut' => false])
        ->assertCookie(CookieConsent::COOKIE_NAME, 'accepted');

    $consent = CookieConsent::sole();
    expect($consent->status)->toBe('accepted')
        ->and($consent->policy_version)->toBe(CookieConsent::POLICY_VERSION)
        ->and($consent->user_id)->toBeNull();
});

it('documents a declined choice and sets the consent cookie', function (): void {
    $this->postJson(route('cookie-consent.store'), ['status' => 'declined'])
        ->assertOk()
        ->assertJson(['status' => 'declined', 'signedOut' => false])
        ->assertCookie(CookieConsent::COOKIE_NAME, 'declined');

    expect(CookieConsent::sole()->status)->toBe('declined');
});

it('rejects an unknown consent status', function (): void {
    $this->postJson(route('cookie-consent.store'), ['status' => 'maybe'])
        ->assertUnprocessable();

    expect(CookieConsent::count())->toBe(0);
});

it('attributes a signed-in decision to the user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('cookie-consent.store'), ['status' => 'accepted'])
        ->assertOk();

    expect(CookieConsent::sole()->user_id)->toBe($user->id);
});

it('stores only a hash of the ip, never the ip itself', function (): void {
    $this->postJson(route('cookie-consent.store'), ['status' => 'accepted']);

    expect(CookieConsent::sole()->ip_hash)->toBe(hash('sha256', '127.0.0.1'));
});

it('keeps the full decision history when consent is withdrawn', function (): void {
    $this->postJson(route('cookie-consent.store'), ['status' => 'accepted']);
    $this->postJson(route('cookie-consent.store'), ['status' => 'declined']);

    expect(CookieConsent::pluck('status')->all())->toBe(['accepted', 'declined']);
});

// --- All-or-nothing: withdrawal signs the user out -----------------------------

it('signs the user out when they withdraw consent', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('cookie-consent.store'), ['status' => 'declined'])
        ->assertOk()
        ->assertJson(['status' => 'declined', 'signedOut' => true]);

    $this->assertGuest();
    // The withdrawal itself is still attributed — logged before logout.
    expect(CookieConsent::sole()->user_id)->toBe($user->id);
});

// --- Consent gate on cookie-backed features ------------------------------------

it('bounces sign-in to home without a consent decision', function (): void {
    $this->get(route('auth.login'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('cookie_consent.required', true);
});

it('bounces sign-in to home when consent was declined', function (): void {
    $this->withCookie(CookieConsent::COOKIE_NAME, 'declined')
        ->get(route('auth.login'))
        ->assertRedirect(route('home'));
});

it('lets a consenting visitor reach sign-in', function (): void {
    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('auth.login'))
        ->assertOk();
});

it('gates every auth step behind consent', function (): void {
    foreach (['auth.email', 'auth.verify', 'auth.password', 'auth.login.verify'] as $route) {
        $this->get(route($route))->assertRedirect(route('home'));
    }
});

it('bounces a signed-in user without consent from the profile', function (): void {
    // e.g. the ~6-month consent cookie expired mid-session: re-prompt at home.
    // (Guests hit the auth middleware first and land on the consent-gated
    // sign-in page instead — either way nothing renders without consent.)
    $this->actingAs(User::factory()->create())
        ->get(route('profile'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('cookie_consent.required', true);
});

it('still sends an unauthenticated but consenting visitor to sign-in for the profile', function (): void {
    $this->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('profile'))
        ->assertRedirect(route('auth.login'));
});

// --- Banner -------------------------------------------------------------------

it('renders the banner with equally prominent accept and decline buttons', function (): void {
    $this->withoutVite()
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Cookies at Street Bites')
        ->assertSee('Accept cookies')
        ->assertSee('Decline cookies');
});
