<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function manage(User $user, Address $address): bool
    {
        if ((int) $address->user_id === (int) $user->id) {
            return true;
        }

        return $address->company_id !== null && $user->isCompanyManager($address->company);
    }
}
