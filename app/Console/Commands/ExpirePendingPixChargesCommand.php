<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('nexosupri:expire-pix-charges')]
#[Description('Expira cobranças PIX dinâmicas pendentes cujo prazo expirou')]
class ExpirePendingPixChargesCommand extends Command
{
    public function handle(): int
    {
        $expiredCount = 0;

        Payment::query()
            ->where('payment_method', 'pix_dynamic')
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($charges) use (&$expiredCount): void {
                foreach ($charges as $charge) {
                    DB::transaction(function () use ($charge, &$expiredCount): void {
                        $locked = Payment::where('id', $charge->id)->lockForUpdate()->first();

                        if (! $locked || $locked->status !== Payment::STATUS_PENDING) {
                            return;
                        }

                        $locked->update([
                            'status' => Payment::STATUS_EXPIRED,
                            'admin_notes' => 'Cobrança PIX expirada automaticamente após o prazo.',
                        ]);

                        AuditLog::record(
                            'pix.charge_expired',
                            $locked,
                            ['status' => Payment::STATUS_PENDING],
                            ['status' => Payment::STATUS_EXPIRED, 'txid' => $locked->txid],
                            $locked->requested_by_user_id,
                            $locked->company_id
                        );

                        $expiredCount++;
                    });
                }
            });

        if ($expiredCount > 0) {
            Cache::forget('admin_pending_counts');
        }

        $this->info("Cobranças PIX expiradas: {$expiredCount}");

        return self::SUCCESS;
    }
}
