<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\ThrottlesAttempts;
use App\Mail\LoginCode;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Step 2 of sign-in: the second factor. The primary credentials were already
 * verified, so here we only confirm the emailed one-time code, then issue the
 * authenticated session. Errors stay generic and attempts are rate-limited so
 * the step can't be brute-forced or used to probe which codes are valid.
 */
class LoginVerify extends Component
{
    use ThrottlesAttempts;

    public string $email = '';

    #[Validate('required|digits:6')]
    public string $code = '';

    public function mount(): void
    {
        $user = $this->pendingUser();

        if (! $user instanceof User) {
            $this->redirectRoute('auth.login');

            return;
        }

        $this->email = $user->email;
    }

    public function verify(): void
    {
        $user = $this->pendingUser();

        if (! $user instanceof User) {
            $this->redirectRoute('auth.login');

            return;
        }

        $this->validate();

        $key = 'login-verify:'.$user->id;

        if ($this->throttled($key, 'code')) {
            return;
        }

        if (! EmailVerification::check($user->email, $this->code)) {
            $this->recordAttempt($key);
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        $this->clearAttempts($key);

        Auth::login($user);

        // Drop the pending marker and rotate the session id to thwart fixation.
        Session::forget('auth.login.pending');
        Session::regenerate();

        $this->redirectRoute('home');
    }

    public function resend(): void
    {
        $user = $this->pendingUser();

        if (! $user instanceof User) {
            $this->redirectRoute('auth.login');

            return;
        }

        $key = 'login-resend:'.$user->id;

        if ($this->throttled($key, 'code', 'requests')) {
            return;
        }

        $this->recordAttempt($key);

        $code = EmailVerification::issueFor($user->email);
        Mail::to($user->email)->send(new LoginCode($code, $user->email));

        session()->flash('status', 'A new code is on its way.');
    }

    public function render(): View
    {
        return view('livewire.auth.login-verify');
    }

    /**
     * The user awaiting second-factor verification, resolved from the session
     * marker set by the password step, or null if there is no pending sign-in.
     */
    private function pendingUser(): ?User
    {
        $id = session('auth.login.pending');

        if (! is_int($id) && ! (is_string($id) && $id !== '')) {
            return null;
        }

        return User::query()->find($id);
    }
}
