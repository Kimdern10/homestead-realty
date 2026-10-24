<?php

namespace App\Enums;

enum EnquiryType: string
{
    case Message = 'message';
    case Tour = 'tour';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Message => 'Message',
            self::Tour => 'Tour request',
        };
    }
}
