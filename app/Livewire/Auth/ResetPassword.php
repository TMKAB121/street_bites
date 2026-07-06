<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Step 3 of password reset: the user has proven email ownership via the one-time
 * code, so collect a new password, update the account, sign them in, and go home.
 * Mirrors sign-up's SetPassword but updates an existing account instead of
 * creating one.
 */
class ResetPassword extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if ($this->verifiedEmail() === null) {
            $this->redirectRoute('auth.password.request');
        }
    }

    public function submit(): void
    {
        $email = $this->verifiedEmail();

        if ($email === null) {
            $this->redirectRoute('auth.password.request');

            return;
        }

        // Password::defaults() carries the app-wide policy (min length + breach
        // check); max:128 caps input length to blunt long-string hashing DoS.
        $this->validate([
            'password' => ['required', 'confirmed', 'max:128', Password::defaults()],
        ]);

        $user = User::query()->where('email', $email)->first();

        // The verified email always maps to a real account (only real accounts
        // are issued a code), but guard against it vanishing mid-flow.
        if (! $user instanceof User) {
            Session::forget(['auth.reset.email', 'auth.reset.verified']);
            $this->redirectRoute('auth.password.request');

            return;
        }

        // The 'hashed' cast re-hashes the plaintext with the app's Argon2id driver
        // on assignment; forceFill keeps it off the mass-assignment path.
        $user->forceFill(['password' => $this->password])->save();

        Auth::login($user);

        Session::forget(['auth.reset.email', 'auth.reset.verified']);
        Session::regenerate();

        $this->redirectRoute('home');
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }

    /**
     * The email confirmed in the verification step, or null if the user hasn't
     * completed it (e.g. deep-linked straight here).
     */
    private function verifiedEmail(): ?string
    {
        $email = session('auth.reset.verified');

        return is_string($email) && $email !== '' ? $email : null;
    }
}
