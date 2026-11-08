<?php

namespace App\Mail;

use App\Models\Listing;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the office an owner sent in a home to check. Sent straight away, no queue.
 */
class SubmissionReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Listing $listing)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address((string) $this->listing->owner_email, (string) $this->listing->owner_name)],
            subject: 'New home from an owner: '.$this->listing->address.' | '.Setting::get('business_name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.submission-received', with: ['reviewUrl' => route('dashboard.submissions')]);
    }
}
