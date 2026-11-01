<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case ApplicationFee = 'application_fee';
    case HoldingDeposit = 'holding_deposit';
    case FeaturedListing = 'featured_listing';
    case Service = 'service';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::ApplicationFee => 'Rental application fee',
            self::HoldingDeposit => 'Holding deposit',
            self::FeaturedListing => 'Featured listing',
            self::Service => 'Service booking',
        };
    }

    /**
     * Get the Flux badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::ApplicationFee => 'blue',
            self::HoldingDeposit => 'violet',
            self::FeaturedListing => 'amber',
            self::Service => 'teal',
        };
    }
}
