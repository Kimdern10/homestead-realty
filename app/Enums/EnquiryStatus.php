<?php

namespace App\Enums;

/**
 * The stages of a lead, in order: New → Contacted → Toured → Offer → Won (closed) or Lost.
 */
enum EnquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Toured = 'toured';
    case Offer = 'offer';
    case Closed = 'closed';
    case Lost = 'lost';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Toured => 'Toured',
            self::Offer => 'Offer made',
            self::Closed => 'Won',
            self::Lost => 'Lost',
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
            self::Toured => 'sky',
            self::Offer => 'violet',
            self::Closed => 'green',
            self::Lost => 'zinc',
        };
    }

    /**
     * Whether the lead still needs work.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Closed, self::Lost], true);
    }

    /**
     * The next stage forward, if any.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::New => self::Contacted,
            self::Contacted => self::Toured,
            self::Toured => self::Offer,
            self::Offer => self::Closed,
            default => null,
        };
    }
}
