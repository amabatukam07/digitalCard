<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->hasAdminAccess()) {
            return true;
        }

        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        return $order->company_id !== null && $user->belongsToCompany((int) $order->company_id);
    }

    public function approve(User $user, Order $order): bool
    {
        if ($order->status !== Order::STATUS_AWAITING_APPROVAL || $order->company_id === null) {
            return false;
        }

        return $user->isCompanyManager($order->company);
    }

    public function cancel(User $user, Order $order): bool
    {
        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        return $order->company_id !== null && $user->isCompanyManager($order->company);
    }

    public function requestReturn(User $user, Order $order): bool
    {
        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        return $order->company_id !== null && $user->belongsToCompany((int) $order->company_id);
    }

    public function manage(User $user): bool
    {
        return $user->canManageLogistics();
    }
}
