<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Payment;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulatedPixGateway implements PaymentGateway
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function createPixCharge(
        Company $company,
        CreditWallet $wallet,
        float $amount,
        ?Contract $contract = null,
        ?int $requestedByUserId = null,
    ): Payment {
        $normalizedAmount = round($amount, 2);
        $txid = 'NSPX'.strtoupper(Str::random(21));
        $payload = self::generateBrCode(
            pixKey: (string) config('nexosupri.pix.key', 'pix@nexosupri.com.br'),
            merchantName: (string) config('nexosupri.pix.merchant_name', 'NEXOSUPRI ABASTECIMENTO'),
            merchantCity: (string) config('nexosupri.pix.merchant_city', 'SAO PAULO'),
            amount: $normalizedAmount,
            txid: $txid,
        );

        $payment = $wallet->payments()->create([
            'company_id' => $company->id,
            'contract_id' => $contract?->id,
            'requested_by_user_id' => $requestedByUserId,
            'payment_method' => 'pix_dynamic',
            'txid' => $txid,
            'pix_payload' => $payload,
            'expires_at' => now()->addMinutes((int) config('nexosupri.pix.expiration_minutes', 30)),
            'amount' => $normalizedAmount,
            'receipt_path' => null,
            'receipt_hash' => null,
            'status' => Payment::STATUS_PENDING,
        ]);

        AuditLog::record(
            'pix.charge_created',
            $payment,
            null,
            ['txid' => $txid, 'amount' => $normalizedAmount, 'contract_id' => $contract?->id],
            $requestedByUserId,
            $company->id
        );

        Cache::forget('admin_pending_counts');

        return $payment;
    }

    /**
     * Processa o webhook PIX de maneira estritamente idempotente (1 crédito = R$ 1,00).
     */
    public function confirmPixWebhook(string $txid, ?int $triggeredByUserId = null): Payment
    {
        return DB::transaction(function () use ($txid, $triggeredByUserId): Payment {
            $payment = Payment::where('txid', $txid)->lockForUpdate()->firstOrFail();

            if ($payment->status === Payment::STATUS_APPROVED) {
                return $payment;
            }

            if ($payment->status !== Payment::STATUS_PENDING) {
                abort(400, 'Esta cobrança PIX não está pendente.');
            }

            if ($payment->expires_at && now()->gt($payment->expires_at)) {
                abort(400, 'Esta cobrança PIX expirou.');
            }

            $wallet = CreditWallet::where('id', $payment->credit_wallet_id)->lockForUpdate()->firstOrFail();
            $creditCents = CartPricing::toCents((float) $payment->amount);
            $creditAmount = CartPricing::toDecimal($creditCents);
            $oldBalance = round((float) $wallet->balance, 2);

            $tx = $this->ledger->credit(
                lockedWallet: $wallet,
                amountCents: $creditCents,
                type: CreditTransaction::TYPE_CREDIT_PURCHASE,
                description: "Contratação de créditos via PIX ({$txid})",
                reference: $payment,
                idempotencyKey: "payment:{$payment->id}:pix",
                actorUserId: $triggeredByUserId ?? $payment->requested_by_user_id,
                reason: 'Confirmação automática de pagamento PIX (1 crédito = R$ 1,00).',
            );

            $newBalance = (float) $tx->balance_after;

            $payment->update([
                'status' => Payment::STATUS_APPROVED,
                'approved_amount' => $creditAmount,
                'admin_notes' => 'Liquidação automática via Webhook PIX',
                'reviewed_at' => now(),
            ]);

            if ($payment->contract_id) {
                $contract = Contract::where('id', $payment->contract_id)->lockForUpdate()->first();
                if ($contract && $contract->status === Contract::STATUS_PENDING_PAYMENT) {
                    $contract->update([
                        'status' => Contract::STATUS_ACTIVE,
                        'starts_at' => now(),
                    ]);
                }
            }

            AuditLog::record(
                'pix.webhook_confirmed',
                $payment,
                ['status' => Payment::STATUS_PENDING, 'wallet_balance' => $oldBalance],
                ['status' => Payment::STATUS_APPROVED, 'txid' => $txid, 'wallet_balance' => $newBalance],
                $triggeredByUserId ?? $payment->requested_by_user_id,
                $payment->company_id
            );

            Cache::forget('admin_pending_counts');

            return $payment;
        });
    }

    public static function generateBrCode(
        string $pixKey,
        string $merchantName,
        string $merchantCity,
        float $amount,
        string $txid = '***',
    ): string {
        $cleanName = substr(preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($merchantName)) ?: 'NEXOSUPRI', 0, 25);
        $cleanCity = substr(preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($merchantCity)) ?: 'SAO PAULO', 0, 15);
        $cleanTxid = substr(preg_replace('/[^A-Za-z0-9*]/', '', $txid) ?: '***', 0, 25);

        $gui = self::emvField('00', 'br.gov.bcb.pix');
        $keyField = self::emvField('01', $pixKey);
        $merchantAccount = self::emvField('26', $gui.$keyField);
        $additionalData = self::emvField('62', self::emvField('05', $cleanTxid));

        $payloadWithoutCrc = implode('', [
            self::emvField('00', '01'),
            $merchantAccount,
            self::emvField('52', '0000'),
            self::emvField('53', '986'),
            self::emvField('54', number_format($amount, 2, '.', '')),
            self::emvField('58', 'BR'),
            self::emvField('59', $cleanName),
            self::emvField('60', $cleanCity),
            $additionalData,
            '6304',
        ]);

        return $payloadWithoutCrc.self::crc16Ccitt($payloadWithoutCrc);
    }

    public static function crc16Ccitt(string $payload): string
    {
        $crc = 0xFFFF;
        $length = strlen($payload);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($bit = 0; $bit < 8; $bit++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    private static function emvField(string $id, string $value): string
    {
        $len = str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT);

        return $id.$len.$value;
    }
}
