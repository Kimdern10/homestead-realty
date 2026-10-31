<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Six-digit codes that prove a visitor owns their email address.
 *
 * Only a hash of the code is kept, for a short time, and a code works once.
 */
class EmailVerificationCodes
{
    /**
     * How long a code stays valid, in minutes.
     */
    public const int MINUTES = 15;

    /**
     * Make a new code for the user (replacing any earlier one) and return it.
     */
    public function create(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->key($user), Hash::make($code), now()->addMinutes(self::MINUTES));

        return $code;
    }

    /**
     * Whether the code is the user's current one. A correct code is used up.
     */
    public function check(User $user, string $code): bool
    {
        $hash = Cache::get($this->key($user));
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (! is_string($hash) || strlen($code) !== 6 || ! Hash::check($code, $hash)) {
            return false;
        }

        Cache::forget($this->key($user));

        return true;
    }

    private function key(User $user): string
    {
        return "email-code:{$user->id}";
    }
}
