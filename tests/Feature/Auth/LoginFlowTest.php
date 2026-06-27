<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use App\Livewire\Auth\LoginVerify;
use App\Mail\LoginCode;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- Entry point ------------------------------------------------------------

it('renders the login form', function (): void {
    $this->withoutVite()
        ->get(route('auth.login'))
        ->assertOk()
        ->assertSee('Sign in');
});

// --- Step 1: primary credentials --------------------------------------------

it('issues a second-factor code for valid credentials without logging in', function (): void {
    Mail::fake();
    $user = User::factory()->create(['password' => 'a-strong-passphrase']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'a-strong-passphrase')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('auth.login.verify'));

    expect(session('auth.login.pending'))->toBe($user->id);
    expect(auth()->check())->toBeFalse();

    Mail::assertSent(LoginCode::class, fn (LoginCode $mail): bool => $mail->hasTo($user->email));
});

it('rejects a wrong password with a generic error and sends nothing', function (): void {
    Mail::fake();
    $user = User::factory()->create(['password' => 'a-strong-passphrase']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'the-wrong-password')
        ->call('submit')
        ->assertHasErrors(['email']);

    expect(session('auth.login.pending'))->toBeNull();
    Mail::assertNothingSent();
});

it('rejects an unknown email with the same generic error', function (): void {
    Mail::fake();

    Livewire::test(Login::class)
        ->set('email', 'nobody@example.com')
        ->set('password', 'any-password-here')
        ->call('submit')
        ->assertHasErrors(['email']);

    Mail::assertNothingSent();
});

// --- Step 2: second factor --------------------------------------------------

it('redirects to login when there is no pending sign-in', function (): void {
    Livewire::test(LoginVerify::class)
        ->assertRedirect(route('auth.login'));
});

it('rejects an incorrect second-factor code', function (): void {
    $user = User::factory()->create();
    EmailVerification::issueFor($user->email);
    session(['auth.login.pending' => $user->id]);

    Livewire::test(LoginVerify::class)
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors(['code']);

    expect(auth()->check())->toBeFalse();
});

it('logs the user in and lands on home with a correct code', function (): void {
    $user = User::factory()->create();
    $code = EmailVerification::issueFor($user->email);
    session(['auth.login.pending' => $user->id]);

    Livewire::test(LoginVerify::class)
        ->set('code', $code)
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(auth()->id())->toBe($user->id);
    expect(session('auth.login.pending'))->toBeNull();
});

// --- Auth-aware navigation --------------------------------------------------

it('shows a login link in the navigation for guests', function (): void {
    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertSee(route('auth.login'));
});

it('swaps the login link for a profile link once authenticated', function (): void {
    $user = User::factory()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Profile')
        ->assertDontSee(route('auth.login'));
});
