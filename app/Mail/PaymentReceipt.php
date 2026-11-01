<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The payer's receipt. Sent straight away, no queue.
 */
class PaymentReceipt extends Mailable
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
        return new Envelope(subject: 'Payment received: '.$this->payment->description.' | '.Setting::get('business_name'));
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.payment-receipt', with: [
            'business' => Setting::get('business_name'),
            'phone' => Setting::get('phone'),
            'url' => route('payments.show', $this->payment),
        ]);
    }
}
