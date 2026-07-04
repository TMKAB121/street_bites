<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\ThrottlesAttempts;
use App\Mail\EmailVerificationCode;
use App\Models\EmailVerification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Step 1 of sign-up: collect a well-formed email, then issue + mail a one-time
 * verification code and move the user on to the verification form.
 */
class EmailEntry extends Component
{
    use ThrottlesAttempts;

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    public function submit(): void
    {
        $this->validate();

        $key = 'verify-email:'.mb_strtolower($this->email);

        if ($this->throttled($key, 'email', 'requests')) {
            return;
        }

        $this->recordAttempt($key);

        $code = EmailVerification::issueFor($this->email);
        Mail::to($this->email)->send(new EmailVerificationCode($code, $this->email));

        session(['auth.email' => $this->email]);

        $this->redirectRoute('auth.verify');
    }

    public function render(): View
    {
        return view('livewire.auth.email-entry');
    }
}
