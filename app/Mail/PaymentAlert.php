<?php

namespace App\Mail;

use App\Enums\PaymentPurpose;
use App\Models\Payment;
use App\Models\RentalApplication;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the agent and the office about a new online payment. Sent straight away, no queue.
 */
class PaymentAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->payment->payer_email, $this->payment->payer_name)],
            subject: $this->payment->purpose->label().' paid: '.$this->payment->amountLabel().' | '.Setting::get('business_name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dashboardUrl = match (true) {
            $this->payment->payable instanceof RentalApplication => route('dashboard.applications.show', $this->payment->payable),
            $this->payment->purpose === PaymentPurpose::Service => route('dashboard.bookings'),
            default => route('dashboard.payments'),
        };

        return new Content(markdown: 'mail.payment-alert', with: ['dashboardUrl' => $dashboardUrl]);
    }
}
