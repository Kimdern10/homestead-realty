<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Condo = 'condo';
    case Townhouse = 'townhouse';
    case Apartment = 'apartment';
    case MultiFamily = 'multi_family';
    case Land = 'land';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::House => 'Single-family home',
            self::Condo => 'Condo',
            self::Townhouse => 'Townhouse',
            self::Apartment => 'Apartment',
            self::MultiFamily => 'Multi-family',
            self::Land => 'Land',
        };
    }

    /**
     * Get the schema.org type used in structured data.
     */
    public function schemaType(): string
    {
        return match ($this) {
            self::House, self::Townhouse => 'SingleFamilyResidence',
            self::Condo, self::Apartment => 'Apartment',
            self::MultiFamily => 'House',
            self::Land => 'Place',
        };
    }

    /**
     * Get every type keyed by value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
