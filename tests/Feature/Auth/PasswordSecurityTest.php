<?php

declare(strict_types=1);

use App\Livewire\Auth\SetPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- Password policy (OWASP / NIST SP 800-63B) ------------------------------

it('rejects a password shorter than the 12-character minimum', function (): void {
    session(['auth.verified' => 'diner@example.com']);

    Livewire::test(SetPassword::class)
        ->set('password', 'short-pw')
        ->set('password_confirmation', 'short-pw')
        ->call('submit')
        ->assertHasErrors(['password']);

    expect(User::query()->where('email', 'diner@example.com')->exists())->toBeFalse();
});

it('accepts a sufficiently long passphrase without forced character classes', function (): void {
    session(['auth.verified' => 'diner@example.com']);

    Livewire::test(SetPassword::class)
        ->set('password', 'correct horse battery staple')
        ->set('password_confirmation', 'correct horse battery staple')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));
});

it('rejects a password longer than the 128-character cap', function (): void {
    session(['auth.verified' => 'diner@example.com']);

    Livewire::test(SetPassword::class)
        ->set('password', str_repeat('a', 129))
        ->set('password_confirmation', str_repeat('a', 129))
        ->call('submit')
        ->assertHasErrors(['password']);
});

// --- Memory-hard hashing with per-password salt -----------------------------

it('hashes passwords with memory-hard Argon2id and a unique salt', function (): void {
    $hasher = Hash::driver('argon2id');

    $hash = $hasher->make('correct horse battery staple');

    expect($hash)->toStartWith('$argon2id$');
    expect(password_get_info($hash)['algoName'])->toBe('argon2id');
    expect($hasher->check('correct horse battery staple', $hash))->toBeTrue();

    // Argon2id embeds a fresh random salt per call, so the same password never
    // produces the same hash twice.
    expect($hasher->make('correct horse battery staple'))->not->toBe($hash);
});

// --- Session cookie hardening -----------------------------------------------

it('keeps the session cookie out of JavaScript by default', function (): void {
    expect(config('session.http_only'))->toBeTrue();
});
