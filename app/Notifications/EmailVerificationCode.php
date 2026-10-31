<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Support\EmailVerificationCodes;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the 6-digit code a new visitor types in to confirm their email. Sent straight away, no queue.
 */
class EmailVerificationCode extends Notification
{
    use Queueable;

    public function __construct(public string $code)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $business = Setting::get('business_name');

        return (new MailMessage)
            ->subject("{$this->code} is your {$business} code")
            ->markdown('mail.email-verification-code', [
                'code' => $this->code,
                'business' => $business,
                'minutes' => EmailVerificationCodes::MINUTES,
            ]);
    }
}
