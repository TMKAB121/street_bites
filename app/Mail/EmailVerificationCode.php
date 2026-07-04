<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;

/**
 * Delivers a one-time verification code to a prospective user, plus an
 * auto-verifying signed magic link back to the verification form.
 */
class EmailVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Street Bites verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: [
                'code' => $this->code,
                'verifyUrl' => $this->verifyUrl(),
            ],
        );
    }

    /**
     * A temporary signed link to the verification form carrying the email and
     * code, so clicking it verifies automatically.
     */
    private function verifyUrl(): string
    {
        return URL::temporarySignedRoute(
            'auth.verify',
            Date::now()->addMinutes(10),
            ['email' => $this->email, 'code' => $this->code],
        );
    }
}
