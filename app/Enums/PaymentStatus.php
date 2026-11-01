<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for payment',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the Flux badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Paid => 'green',
            self::Failed => 'red',
        };
    }
}
