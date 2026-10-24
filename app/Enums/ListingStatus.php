<?php

namespace App\Enums;

enum ListingStatus: string
{
    case ForSale = 'for_sale';
    case Pending = 'pending';
    case ForRent = 'for_rent';
    case Sold = 'sold';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::ForSale => 'For sale',
            self::Pending => 'Pending',
            self::ForRent => 'For rent',
            self::Sold => 'Sold',
        };
    }

    /**
     * Whether the price is a monthly rent rather than a sale price.
     */
    public function isRental(): bool
    {
        return $this === self::ForRent;
    }

    /**
     * Whether a visitor can still buy, rent or tour the home.
     */
    public function isAvailable(): bool
    {
        return in_array($this, [self::ForSale, self::ForRent], true);
    }

    /**
     * Get the colour of the status badge (a CSS modifier).
     */
    public function tone(): string
    {
        return match ($this) {
            self::ForSale => 'sale',
            self::Pending => 'pending',
            self::ForRent => 'rent',
            self::Sold => 'sold',
        };
    }

    /**
     * Get the statuses a visitor can search by, keyed by value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
