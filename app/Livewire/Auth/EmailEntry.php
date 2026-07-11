<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\ThrottlesAttempts;
use App\Mail\EmailVerificationCode;
use App\Models\EmailVerification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * Step 1 of sign-up: collect a well-formed email, then issue + mail a one-time
 * verification code and move the user on to the verification form.
 */
class EmailEntry extends Component
{
    use ThrottlesAttempts;

    /**
     * The second bucket for sign-up code issuing: the trait's per-email limit
     * caps abuse of one address, but an anonymous visitor rotating addresses
     * gets a fresh bucket each time — so sends are also capped per IP. 10 per
     * rolling hour stops address-rotation mail pumping (a bounce-rate risk
     * with any mail provider) while a shared NAT only trips it past 10
     * distinct sign-ups in an hour. Public: VerifyCode::resend() shares the
     * bucket.
     */
    public const int SEND_IP_MAX_ATTEMPTS = 10;

    public const int SEND_IP_DECAY_SECONDS = 3600;

    public string $email = '';

    /**
     * Sign-up is the one flow that mails an arbitrary visitor-typed address,
     * so the domain must resolve (MX/A) before we send to it — typos and junk
     * bounce, and bounces cost sender reputation. The dns check is skipped
     * under the test suite (tests never hit the network — same pattern as the
     * HIBP check in AppServiceProvider::configurePasswordPolicy()).
     *
     * @return array<string, string>
     */
    protected function rules(): array
    {
        $dns = app()->runningUnitTests() ? '' : ',dns';

        return ['email' => "required|email:rfc{$dns}|max:255"];
    }

    public function submit(): void
    {
        $this->validate();

        // Two buckets: per-email (5/min) and per-IP (10/hr) — the IP cap is
        // what stops an address-rotating visitor from pumping mail.
        $key = 'verify-email:'.mb_strtolower($this->email);
        $ipKey = 'verify-email-ip:'.request()->ip();

        if ($this->throttled($key, 'email', 'requests')
            || $this->throttled($ipKey, 'email', 'requests', self::SEND_IP_MAX_ATTEMPTS)) {
            return;
        }

        $this->recordAttempt($key);
        $this->recordAttempt($ipKey, self::SEND_IP_DECAY_SECONDS);

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
