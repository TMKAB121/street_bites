<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers the one-time second-factor code for an in-progress sign-in. Unlike
 * the sign-up mail this carries no auto-verifying magic link — the code must be
 * entered on the device that started the login.
 */
class LoginCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Street Bites login code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-code',
            with: [
                'code' => $this->code,
            ],
        );
    }
}
