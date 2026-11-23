<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function manage(User $user, Address $address): bool
    {
        return (int) $address->user_id === (int) $user->id;
    }
}
