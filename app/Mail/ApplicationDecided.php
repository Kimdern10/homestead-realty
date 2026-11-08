<?php

namespace App\Mail;

use App\Enums\ApplicationStatus;
use App\Enums\PaymentPurpose;
use App\Models\RentalApplication;
use App\Models\Setting;
use App\Services\Payments\PaymentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Tells the applicant their rental application was approved (with a link to pay the
 * holding deposit when that is switched on) or not approved. Sent straight away, no queue.
 */
class ApplicationDecided extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * How long the deposit link in the email keeps working.
     */
    public const int LINK_DAYS = 14;

    public function __construct(public RentalApplication $application)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $approved = $this->application->status === ApplicationStatus::Approved;
        $agent = $this->application->listing->agent;

        return new Envelope(
            replyTo: [new Address($agent->contactEmail(), $agent->name)],
            subject: ($approved ? 'Approved: ' : 'Update on your application: ').$this->application->listing->address.' | '.Setting::get('business_name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $payments = app(PaymentManager::class);
        $canPay = $this->application->canPayDeposit();

        return new Content(markdown: 'mail.application-decided', with: [
            'approved' => $this->application->status === ApplicationStatus::Approved,
            'depositUrl' => $canPay
                ? URL::temporarySignedRoute('listings.hold', now()->addDays(self::LINK_DAYS), ['listing' => $this->application->listing, 'application' => $this->application->id])
                : null,
            'depositAmount' => $canPay ? $payments->money($payments->price(PaymentPurpose::HoldingDeposit)) : null,
            'listingUrl' => route('listings.show', $this->application->listing),
            'searchUrl' => route('search', ['status' => 'for_rent', 'q' => $this->application->listing->city->name]),
        ]);
    }
}
