<?php

namespace App\Contracts;

use App\Models\Company;
use App\Models\Contract;
use App\Models\CreditWallet;
use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Cria uma cobrança PIX com txid único e BR Code válido (CRC16-CCITT) para contratação de créditos.
     */
    public function createPixCharge(
        Company $company,
        CreditWallet $wallet,
        float $amount,
        ?Contract $contract = null,
        ?int $requestedByUserId = null,
    ): Payment;

    /**
     * Processa confirmação de pagamento PIX de forma idempotente pelo txid, liberando créditos 1:1.
     */
    public function confirmPixWebhook(string $txid, ?int $triggeredByUserId = null): Payment;
}
