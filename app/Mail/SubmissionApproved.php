<?php

namespace App\Mail;

use App\Models\Listing;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the owner their home is now live on the website. Sent straight away, no queue.
 */
class SubmissionApproved extends Mailable
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
        return new Envelope(subject: 'Your home is live: '.$this->listing->address.' | '.Setting::get('business_name'));
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.submission-approved', with: ['listingUrl' => route('listings.show', $this->listing)]);
    }
}
