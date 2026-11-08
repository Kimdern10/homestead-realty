<?php

namespace App\Enums;

enum ListingStatus: string
{
    case ForSale = 'for_sale';
    case Pending = 'pending';
    case ForRent = 'for_rent';
    case Sold = 'sold';
    case Rented = 'rented';

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
            self::Rented => 'Rented',
        };
    }

    /**
     * Whether the price is a monthly rent rather than a sale price.
     */
    public function isRental(): bool
    {
        return in_array($this, [self::ForRent, self::Rented], true);
    }

    /**
     * Whether a visitor can still buy, rent or tour the home.
     */
    public function isAvailable(): bool
    {
        return in_array($this, [self::ForSale, self::ForRent], true);
    }

    /**
     * Whether the home is off the market for good: sold or rented out.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Sold, self::Rented], true);
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
            self::Rented => 'rented',
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
