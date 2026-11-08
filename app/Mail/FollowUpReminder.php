<?php

namespace App\Mail;

use App\Models\Agent;
use App\Models\Enquiry;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The agent's list of leads to call back today. Sent straight away, no queue.
 */
class FollowUpReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Enquiry>  $enquiries
     */
    public function __construct(public Agent $agent, public Collection $enquiries)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $count = $this->enquiries->count();

        return new Envelope(subject: "Follow up today: {$count} ".str('lead')->plural($count).' | '.Setting::get('business_name'));
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.follow-up-reminder', with: ['dashboardUrl' => route('dashboard.enquiries.index', ['view' => 'board'])]);
    }
}
