<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Step 3 of sign-up: the user has proven email ownership, so collect a password,
 * create the account (name derived from the email), log them in, and go home.
 */
class SetPassword extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if ($this->verifiedEmail() === null) {
            $this->redirectRoute('auth.email');
        }
    }

    public function submit(): void
    {
        $email = $this->verifiedEmail();

        if ($email === null) {
            $this->redirectRoute('auth.email');

            return;
        }

        $this->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (User::query()->where('email', $email)->exists()) {
            $this->addError('password', 'An account already exists for this email. Please sign in instead.');

            return;
        }

        $user = User::query()->create([
            'name' => Str::before($email, '@'),
            'email' => $email,
            'password' => $this->password,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        Auth::login($user);

        Session::forget(['auth.email', 'auth.verified']);
        Session::regenerate();

        $this->redirectRoute('home');
    }

    public function render(): View
    {
        return view('livewire.auth.set-password');
    }

    /**
     * The email confirmed in the verification step, or null if the user hasn't
     * completed it (e.g. deep-linked straight here).
     */
    private function verifiedEmail(): ?string
    {
        $email = session('auth.verified');

        return is_string($email) && $email !== '' ? $email : null;
    }
}
