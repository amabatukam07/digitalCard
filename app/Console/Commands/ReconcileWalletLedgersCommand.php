<?php

namespace App\Console\Commands;

use App\Models\VirtualCard;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('digitalcard:reconcile-ledgers')]
#[Description('Reconcilia os saldos das carteiras com o somatório das transações do ledger')]
class ReconcileWalletLedgersCommand extends Command
{
    public function handle(WalletLedgerService $ledger): int
    {
        $checked = 0;
        $mismatches = [];

        VirtualCard::query()->chunkById(100, function ($cards) use ($ledger, &$checked, &$mismatches): void {
            foreach ($cards as $card) {
                $checked++;
                $result = $ledger->reconcile($card);
                if (! $result['balanced']) {
                    $mismatches[] = $result;
                }
            }
        });

        if ($mismatches !== []) {
            foreach ($mismatches as $row) {
                $this->error(sprintf(
                    'Divergência no cartão #%d: saldo=R$ %.2f, ledger=R$ %.2f, diferença=R$ %.2f',
                    $row['card_id'],
                    CartPricing::toDecimal($row['card_balance_cents']),
                    CartPricing::toDecimal($row['ledger_sum_cents']),
                    CartPricing::toDecimal($row['difference_cents'])
                ));
            }

            return self::FAILURE;
        }

        $this->info("Reconciliação concluída sem divergências ({$checked} cartões verificados).");

        return self::SUCCESS;
    }
}
