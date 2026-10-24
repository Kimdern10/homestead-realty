<?php

namespace App\Mail;

use App\Enums\EnquiryType;
use App\Models\Enquiry;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the agent (and the office) about a new message or tour request. Sent straight away, no queue.
 */
class EnquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Enquiry $enquiry)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $about = $this->enquiry->listing?->address;
        $subject = $this->enquiry->type === EnquiryType::Tour
            ? 'Tour request: '.($about ?? 'a home')
            : 'New message'.($about ? ' about '.$about : '');

        return new Envelope(
            replyTo: [new Address($this->enquiry->email, $this->enquiry->name)],
            subject: $subject.' | '.Setting::get('business_name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.enquiry-received',
            with: [
                'dashboardUrl' => route('dashboard.enquiries.show', $this->enquiry),
                'listingUrl' => $this->enquiry->listing ? route('listings.show', $this->enquiry->listing) : null,
            ],
        );
    }
}
