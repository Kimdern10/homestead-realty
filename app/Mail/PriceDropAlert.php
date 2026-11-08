<?php

namespace App\Mail;

use App\Models\Listing;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Price cut on a home you saved". Sent straight away, no queue.
 */
class PriceDropAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Listing>  $homes
     * @param  array<int, int>  $oldPrices  listing id => price when last told
     */
    public function __construct(public User $user, public Collection $homes, public array $oldPrices)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->homes->count() === 1
            ? 'Price cut: '.$this->homes->first()?->address
            : 'Price cuts on '.$this->homes->count().' homes you saved';

        return new Envelope(subject: $subject.' | '.Setting::get('business_name'));
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.price-drop-alert', with: ['savedUrl' => route('saved')]);
    }
}
