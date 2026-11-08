<?php

namespace App\Mail;

use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "3 new homes match Austin under $600k". Sent straight away, no queue.
 */
class SavedSearchAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Listing>  $homes
     */
    public function __construct(public SavedSearch $search, public Collection $homes, public int $total)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->total.' new '.str('home')->plural($this->total).' for “'.$this->search->name.'” | '.Setting::get('business_name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.saved-search-alert', with: [
            'searchUrl' => $this->search->url(),
            'manageUrl' => route('saved'),
        ]);
    }
}
