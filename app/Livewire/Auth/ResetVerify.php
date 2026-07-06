<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\ThrottlesAttempts;
use App\Mail\PasswordResetCode;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Step 2 of password reset: confirm ownership of the email by entering the
 * 6-digit code. Unlike sign-up there is no magic-link auto-verify — the reset
 * mail carries no link — so the code must be entered here. Errors stay generic
 * and attempts are rate-limited so the step can't be brute-forced.
 */
class ResetVerify extends Component
{
    use ThrottlesAttempts;

    public string $email = '';

    #[Validate('required|digits:6')]
    public string $code = '';

    public function mount(): void
    {
        $email = session('auth.reset.email');
        $this->email = is_string($email) ? $email : '';

        if ($this->email === '') {
            $this->redirectRoute('auth.password.request');
        }
    }

    public function verify(): void
    {
        if ($this->email === '') {
            $this->redirectRoute('auth.password.request');

            return;
        }

        $this->validate();

        $key = 'reset-verify:'.mb_strtolower($this->email);

        if ($this->throttled($key, 'code')) {
            return;
        }

        if (! EmailVerification::check($this->email, $this->code)) {
            $this->recordAttempt($key);
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        $this->clearAttempts($key);

        session(['auth.reset.verified' => $this->email]);

        $this->redirectRoute('auth.password.reset');
    }

    public function resend(): void
    {
        if ($this->email === '') {
            $this->redirectRoute('auth.password.request');

            return;
        }

        $key = 'reset-resend:'.mb_strtolower($this->email);

        if ($this->throttled($key, 'code', 'requests')) {
            return;
        }

        $this->recordAttempt($key);

        // Same anti-enumeration stance as the request step: only a real account
        // gets a fresh code, but the on-screen status is always identical.
        $user = User::query()->where('email', $this->email)->first();

        if ($user instanceof User) {
            $code = EmailVerification::issueFor($user->email);
            Mail::to($user->email)->send(new PasswordResetCode($code, $user->email));
        }

        session()->flash('status', 'A new code is on its way.');
    }

    public function render(): View
    {
        return view('livewire.auth.reset-verify');
    }
}
