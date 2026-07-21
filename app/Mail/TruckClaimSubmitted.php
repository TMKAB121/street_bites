<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the moderation admins that a visitor asked to claim an unclaimed
 * truck. Queued (ShouldQueue) so it never slows or fails the claim submission,
 * riding the same Redis queue as the rest of the app's background work. Sent to
 * `config('admin.emails')`; a no-op when the allowlist is empty. Mirrors
 * TruckHeldForReview — the admin reviews it on the queue's Claims tab.
 */
class TruckClaimSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $truckName,
        public string $claimantEmail,
        public ?string $message,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Street Bites: a truck claim is awaiting review',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.truck-claim-submitted',
        );
    }
}
