<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /**
     * Admins and agents can add listings.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || ($user->isAgent() && $user->agent !== null);
    }

    /**
     * Admins can edit any listing; agents only their own.
     */
    public function update(User $user, Listing $listing): bool
    {
        return $user->isAdmin() || ($user->isAgent() && $listing->agent_id === $user->agent?->id);
    }

    /**
     * Same rule as editing.
     */
    public function delete(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }
}
