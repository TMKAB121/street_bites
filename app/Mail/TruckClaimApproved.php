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
 * Tells a claimant their truck claim was approved and they now manage the truck
 * from their profile. Queued (ShouldQueue) so approving in the moderation queue
 * never blocks on mail. Sent to the claimant's own email.
 */
class TruckClaimApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $truckName,
        public string $truckUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Street Bites: your truck claim was approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.truck-claim-approved',
        );
    }
}
