<?php

declare(strict_types=1);

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\ResetVerify;
use App\Mail\PasswordResetCode;
use App\Models\CookieConsent;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- Entry point ------------------------------------------------------------

it('links to the reset flow from the login form', function (): void {
    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('auth.login'))
        ->assertOk()
        ->assertSee(route('auth.password.request'));
});

it('renders the forgot-password form', function (): void {
    $this->withoutVite()
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('auth.password.request'))
        ->assertOk()
        ->assertSee('Reset your password');
});

// --- Step 1: request a code -------------------------------------------------

it('mails a reset code for a real account and advances to the verify step', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('auth.password.verify'));

    expect(session('auth.reset.email'))->toBe($user->email);

    Mail::assertSent(PasswordResetCode::class, fn (PasswordResetCode $mail): bool => $mail->hasTo($user->email));
});

it('advances identically for an unknown email but sends nothing (anti-enumeration)', function (): void {
    Mail::fake();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('auth.password.verify'));

    expect(session('auth.reset.email'))->toBe('nobody@example.com');
    Mail::assertNothingSent();
});

// --- Step 2: verify the code ------------------------------------------------

it('redirects to the request step when no reset is in progress', function (): void {
    Livewire::test(ResetVerify::class)
        ->assertRedirect(route('auth.password.request'));
});

it('rejects an incorrect reset code', function (): void {
    $user = User::factory()->create();
    EmailVerification::issueFor($user->email);
    session(['auth.reset.email' => $user->email]);

    Livewire::test(ResetVerify::class)
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors(['code']);

    expect(session('auth.reset.verified'))->toBeNull();
});

it('accepts a correct code and advances to the new-password step', function (): void {
    $user = User::factory()->create();
    $code = EmailVerification::issueFor($user->email);
    session(['auth.reset.email' => $user->email]);

    Livewire::test(ResetVerify::class)
        ->set('code', $code)
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('auth.password.reset'));

    expect(session('auth.reset.verified'))->toBe($user->email);
});

// --- Step 3: set the new password -------------------------------------------

it('redirects to the request step when the email was never verified', function (): void {
    Livewire::test(ResetPassword::class)
        ->assertRedirect(route('auth.password.request'));
});

it('updates the password, signs the user in, and clears the reset session', function (): void {
    $user = User::factory()->create(['password' => 'the-old-passphrase']);
    session(['auth.reset.verified' => $user->email]);

    Livewire::test(ResetPassword::class)
        ->set('password', 'a-brand-new-passphrase')
        ->set('password_confirmation', 'a-brand-new-passphrase')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(auth()->id())->toBe($user->id);
    expect(session('auth.reset.verified'))->toBeNull();
    expect(Hash::check('a-brand-new-passphrase', $user->fresh()->password))->toBeTrue();
});

it('enforces the password policy on the new password', function (): void {
    $user = User::factory()->create();
    session(['auth.reset.verified' => $user->email]);

    Livewire::test(ResetPassword::class)
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('submit')
        ->assertHasErrors(['password']);

    expect(auth()->check())->toBeFalse();
});
