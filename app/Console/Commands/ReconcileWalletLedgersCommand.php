<?php

namespace App\Console\Commands;

use App\Models\CreditWallet;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nexosupri:reconcile-ledgers')]
#[Description('Reconcilia os saldos das carteiras de créditos empresariais com o extrato imutável')]
class ReconcileWalletLedgersCommand extends Command
{
    public function handle(WalletLedgerService $ledger): int
    {
        $checked = 0;
        $mismatches = [];

        CreditWallet::query()->chunkById(100, function ($wallets) use ($ledger, &$checked, &$mismatches): void {
            foreach ($wallets as $wallet) {
                $checked++;
                $result = $ledger->reconcile($wallet);
                if (! $result['balanced']) {
                    $mismatches[] = $result;
                }
            }
        });

        if ($mismatches !== []) {
            foreach ($mismatches as $row) {
                $this->error(sprintf(
                    'Divergência na carteira #%d (empresa #%d): saldo=%.2f créditos, extrato=%.2f, diferença=%.2f',
                    $row['wallet_id'],
                    $row['company_id'],
                    CartPricing::toDecimal($row['wallet_balance_cents']),
                    CartPricing::toDecimal($row['ledger_sum_cents']),
                    CartPricing::toDecimal($row['difference_cents'])
                ));
            }

            return self::FAILURE;
        }

        $this->info("Reconciliação concluída sem divergências ({$checked} carteiras verificadas).");

        return self::SUCCESS;
    }
}
