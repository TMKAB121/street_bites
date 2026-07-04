<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\ThrottlesAttempts;
use App\Mail\LoginCode;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Step 1 of sign-in: validate the primary credentials (NIST stepped auth). On
 * success we issue an emailed one-time code as the second factor and hand off
 * to the verification step — the user is not logged in until that succeeds.
 */
class Login extends Component
{
    use ThrottlesAttempts;

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectRoute('home');
        }
    }

    public function submit(): void
    {
        $this->validate();

        $key = 'login:'.request()->ip().'|'.mb_strtolower($this->email);

        if ($this->throttled($key, 'email')) {
            return;
        }

        $user = User::query()->where('email', $this->email)->first();

        // A single generic error for both an unknown email and a wrong password
        // avoids leaking which accounts exist (credential-stuffing defence).
        if (! $user instanceof User || ! Hash::check($this->password, $user->password)) {
            $this->recordAttempt($key);
            $this->addError('email', 'These credentials do not match our records.');
            $this->reset('password');

            return;
        }

        $this->clearAttempts($key);

        // Honour rehash_on_login: upgrade a legacy bcrypt hash to Argon2id now
        // that we hold the plaintext. The 'hashed' cast re-hashes on assignment.
        if (Hash::needsRehash($user->password)) {
            $user->password = $this->password;
            $user->save();
        }

        // Credentials are good — issue the second factor and move on. We persist
        // only the pending user id in the session, never the password.
        $code = EmailVerification::issueFor($user->email);
        Mail::to($user->email)->send(new LoginCode($code, $user->email));

        session(['auth.login.pending' => $user->id]);

        $this->reset('password');

        $this->redirectRoute('auth.login.verify');
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
