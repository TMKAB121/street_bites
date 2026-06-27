<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Mail\EmailVerificationCode;
use App\Models\EmailVerification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Step 2 of sign-up: confirm ownership of the email by entering the 6-digit
 * code. A valid signed magic link from the email auto-verifies on mount.
 */
class VerifyCode extends Component
{
    public string $email = '';

    #[Validate('required|digits:6')]
    public string $code = '';

    public function mount(Request $request): void
    {
        $email = $request->query('email') ?? session('auth.email');
        $this->email = is_string($email) ? $email : '';

        if ($this->email === '') {
            $this->redirectRoute('auth.email');

            return;
        }

        // Arrived via the signed magic link — verify automatically.
        if ($request->hasValidSignature() && $request->filled('code')) {
            $this->code = (string) $request->query('code');

            $this->attempt();
        }
    }

    public function verify(): void
    {
        $this->attempt();
    }

    public function resend(): void
    {
        $key = 'verify-email:'.mb_strtolower($this->email);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', 'Too many requests. Please wait a moment and try again.');

            return;
        }

        RateLimiter::hit($key, 60);

        $code = EmailVerification::issueFor($this->email);
        Mail::to($this->email)->send(new EmailVerificationCode($code, $this->email));

        session()->flash('status', 'A new code is on its way.');
    }

    public function render(): View
    {
        return view('livewire.auth.verify-code');
    }

    private function attempt(): void
    {
        $this->validate();

        if (! EmailVerification::check($this->email, $this->code)) {
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        session(['auth.verified' => $this->email]);

        $this->redirectRoute('auth.password');
    }
}
