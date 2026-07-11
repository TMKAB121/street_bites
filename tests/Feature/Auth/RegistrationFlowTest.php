<?php

declare(strict_types=1);

use App\Livewire\Auth\EmailEntry;
use App\Livewire\Auth\SetPassword;
use App\Livewire\Auth\VerifyCode;
use App\Mail\EmailVerificationCode;
use App\Models\CookieConsent;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- Entry points -----------------------------------------------------------

it('exposes the sign-up flow from the login page', function (): void {
    // Guests reach Login from the home nav; sign-up is offered from there.
    // Auth pages sit behind cookie consent (RequireCookieConsent).
    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('auth.login'))
        ->assertOk()
        ->assertSee(route('auth.email'));
});

it('renders the email entry form', function (): void {
    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('auth.email'))
        ->assertOk()
        ->assertSee('Send code');
});

// --- Step 1: email entry ----------------------------------------------------

it('rejects an improperly formatted email', function (): void {
    Mail::fake();

    Livewire::test(EmailEntry::class)
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors(['email']);

    Mail::assertNothingSent();
});

it('issues a code and mails it for a valid email', function (): void {
    Mail::fake();

    Livewire::test(EmailEntry::class)
        ->set('email', 'diner@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('auth.verify'));

    expect(EmailVerification::query()->where('email', 'diner@example.com')->exists())->toBeTrue();
    expect(session('auth.email'))->toBe('diner@example.com');

    Mail::assertSent(
        EmailVerificationCode::class,
        fn (EmailVerificationCode $mail): bool => $mail->hasTo('diner@example.com'),
    );
});

// --- Send rate limiting ------------------------------------------------------
// Sign-up mails whatever address the visitor types, so sends are capped twice:
// per email (5/min) and per IP (10/hr — the address-rotation backstop).

it('throttles repeated code requests for the same email', function (): void {
    Mail::fake();

    $component = Livewire::test(EmailEntry::class);

    foreach (range(1, 5) as $ignored) {
        $component->set('email', 'diner@example.com')
            ->call('submit')
            ->assertHasNoErrors();
    }

    $component->set('email', 'diner@example.com')
        ->call('submit')
        ->assertHasErrors(['email']);

    Mail::assertSentCount(5);
});

it('throttles code requests per IP across different emails', function (): void {
    Mail::fake();

    $component = Livewire::test(EmailEntry::class);

    foreach (range(1, 10) as $i) {
        $component->set('email', "diner{$i}@example.com")
            ->call('submit')
            ->assertHasNoErrors();
    }

    // An 11th distinct address from the same IP: each per-email bucket is
    // fresh, but the shared IP bucket is exhausted.
    $component->set('email', 'diner11@example.com')
        ->call('submit')
        ->assertHasErrors(['email']);

    Mail::assertSentCount(10);
});

it('counts resends against the same per-IP bucket as first sends', function (): void {
    Mail::fake();

    $entry = Livewire::test(EmailEntry::class);

    foreach (range(1, 10) as $i) {
        $entry->set('email', "diner{$i}@example.com")->call('submit');
    }

    // diner1's own per-email bucket has one hit (well under 5), so a denial
    // here can only come from the shared IP bucket.
    session(['auth.email' => 'diner1@example.com']);

    Livewire::test(VerifyCode::class)
        ->call('resend')
        ->assertHasErrors(['code']);

    Mail::assertSentCount(10);
});

// --- Step 2: code verification ---------------------------------------------

it('advances to the password step when the code is correct', function (): void {
    $code = EmailVerification::issueFor('diner@example.com');
    session(['auth.email' => 'diner@example.com']);

    Livewire::test(VerifyCode::class)
        ->set('code', $code)
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('auth.password'));

    expect(session('auth.verified'))->toBe('diner@example.com');
});

it('rejects an incorrect code', function (): void {
    EmailVerification::issueFor('diner@example.com');
    session(['auth.email' => 'diner@example.com']);

    Livewire::test(VerifyCode::class)
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors(['code']);

    expect(session('auth.verified'))->toBeNull();
});

it('rejects an expired code', function (): void {
    $code = EmailVerification::issueFor('diner@example.com');
    EmailVerification::query()->where('email', 'diner@example.com')
        ->update(['expires_at' => now()->subMinute()]);
    session(['auth.email' => 'diner@example.com']);

    Livewire::test(VerifyCode::class)
        ->set('code', $code)
        ->call('verify')
        ->assertHasErrors(['code']);
});

it('burns the code after too many wrong attempts', function (): void {
    $code = EmailVerification::issueFor('diner@example.com');

    foreach (range(1, 5) as $ignored) {
        expect(EmailVerification::check('diner@example.com', '111111'))->toBeFalse();
    }

    // Even the right code no longer works once the attempt cap is hit.
    expect(EmailVerification::check('diner@example.com', $code))->toBeFalse();
});

it('auto-verifies via a valid signed magic link', function (): void {
    $code = EmailVerification::issueFor('diner@example.com');

    $url = URL::temporarySignedRoute('auth.verify', now()->addMinutes(10), [
        'email' => 'diner@example.com',
        'code' => $code,
    ]);

    // The magic link lands on the consent-gated auth.verify route, so the
    // browser needs the consent cookie (set when sign-up began there).
    $this->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get($url)
        ->assertRedirect(route('auth.password'));

    expect(session('auth.verified'))->toBe('diner@example.com');
});

it('redirects to the email step when no email is pending', function (): void {
    Livewire::test(VerifyCode::class)
        ->assertRedirect(route('auth.email'));
});

// --- Step 3: set password ---------------------------------------------------

it('blocks the password step until the email is verified', function (): void {
    Livewire::test(SetPassword::class)
        ->assertRedirect(route('auth.email'));
});

it('creates the account, derives the name, and logs the user in', function (): void {
    session(['auth.verified' => 'diner@example.com']);

    Livewire::test(SetPassword::class)
        ->set('password', 'super-secret-pw')
        ->set('password_confirmation', 'super-secret-pw')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::query()->where('email', 'diner@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('diner');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('super-secret-pw', $user->password))->toBeTrue();
    expect(auth()->check())->toBeTrue();
    expect(session('auth.verified'))->toBeNull();
});

it('rejects mismatched password confirmation', function (): void {
    session(['auth.verified' => 'diner@example.com']);

    Livewire::test(SetPassword::class)
        ->set('password', 'super-secret-pw')
        ->set('password_confirmation', 'different-pw')
        ->call('submit')
        ->assertHasErrors(['password']);

    expect(User::query()->where('email', 'diner@example.com')->exists())->toBeFalse();
});

it('rejects an email that already has an account', function (): void {
    User::query()->create([
        'name' => 'Existing',
        'email' => 'diner@example.com',
        'password' => 'existing-secret',
    ]);
    session(['auth.verified' => 'diner@example.com']);

    Livewire::test(SetPassword::class)
        ->set('password', 'another-secret-pw')
        ->set('password_confirmation', 'another-secret-pw')
        ->call('submit')
        ->assertHasErrors(['password']);
});
