<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Agent = 'agent';
    case Member = 'member';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Agent => 'Agent',
            self::Member => 'Member',
        };
    }
}
