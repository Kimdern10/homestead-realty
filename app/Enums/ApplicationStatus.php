<?php

namespace App\Enums;

/**
 * Where a rental application or a service booking stands.
 */
enum ApplicationStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case New = 'new';
    case Approved = 'approved';
    case Declined = 'declined';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::New => 'New (paid)',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
        };
    }

    /**
     * The wording the applicant sees in their account.
     */
    public function applicantLabel(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Fee not paid yet',
            self::New => 'Waiting for review',
            self::Approved => 'Approved',
            self::Declined => 'Not approved',
        };
    }

    /**
     * Get the Flux badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'zinc',
            self::New => 'red',
            self::Approved => 'green',
            self::Declined => 'zinc',
        };
    }
}
