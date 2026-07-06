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
 * Step 1 of password reset: collect the account email and issue a one-time code.
 *
 * Anti-enumeration: the step always advances to the verification screen and shows
 * the same outcome whether or not an account exists — a code is only actually
 * mailed when the email belongs to a real account, so the response never reveals
 * which addresses are registered.
 */
class ForgotPassword extends Component
{
    use ThrottlesAttempts;

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    public function submit(): void
    {
        $this->validate();

        $key = 'reset-request:'.request()->ip().'|'.mb_strtolower($this->email);

        if ($this->throttled($key, 'email', 'requests')) {
            return;
        }

        $this->recordAttempt($key);

        // Only issue and mail a code for a real account; a non-account email is
        // carried forward all the same so the outcome can't be used to probe.
        $user = User::query()->where('email', $this->email)->first();

        if ($user instanceof User) {
            $code = EmailVerification::issueFor($user->email);
            Mail::to($user->email)->send(new PasswordResetCode($code, $user->email));
        }

        session(['auth.reset.email' => $this->email]);

        $this->redirectRoute('auth.password.verify');
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
