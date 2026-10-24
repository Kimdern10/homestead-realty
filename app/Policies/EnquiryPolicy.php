<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

class EnquiryPolicy
{
    /**
     * Admins see every enquiry; agents only those sent to them.
     */
    public function view(User $user, Enquiry $enquiry): bool
    {
        return $user->isAdmin() || ($user->isAgent() && $enquiry->agent_id !== null && $enquiry->agent_id === $user->agent?->id);
    }

    /**
     * Same rule as viewing.
     */
    public function update(User $user, Enquiry $enquiry): bool
    {
        return $this->view($user, $enquiry);
    }

    /**
     * Only admins can delete enquiries.
     */
    public function delete(User $user, Enquiry $enquiry): bool
    {
        return $user->isAdmin();
    }
}
