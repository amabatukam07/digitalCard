<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Apenas membros da empresa titular ou equipe autorizada do backoffice podem visualizar o comprovante.
     */
    public function viewReceipt(User $user, Payment $payment): bool
    {
        if ($user->hasAdminAccess()) {
            return true;
        }

        return $payment->company_id !== null && $user->belongsToCompany((int) $payment->company_id);
    }

    /**
     * Apenas Financeiro/Admin que não seja o próprio solicitante pode aprovar ou recusar um pagamento.
     */
    public function review(User $user, Payment $payment): bool
    {
        if (! $user->canManageFinance()) {
            return false;
        }

        return (int) $payment->requested_by_user_id !== (int) $user->id;
    }
}
