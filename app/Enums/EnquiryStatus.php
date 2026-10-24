<?php

namespace App\Enums;

enum EnquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Closed = 'closed';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get the Flux badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'red',
            self::Contacted => 'amber',
            self::Closed => 'zinc',
        };
    }
}
