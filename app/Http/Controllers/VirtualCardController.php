<?php

namespace App\Http\Controllers;

use App\Actions\Wallet\TransferBalanceAction;
use App\Contracts\PaymentGateway;
use App\Models\AuditLog;
use App\Models\CardTransaction;
use App\Models\GiftCard;
use App\Models\Product;
use App\Models\Recharge;
use App\Models\User;
use App\Models\VirtualCard;
use App\Notifications\RechargeReviewedNotification;
use App\Services\CardPinGuard;
use App\Services\CartPricing;
use App\Services\LoyaltyTierService;
use App\Services\Payment\PixQrCodeSvgGenerator;
use App\Services\WalletLedgerService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VirtualCardController extends Controller
{
    public const PIN_MAX_ATTEMPTS = 5;

    public const PIN_DECAY_SECONDS = 900;

    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function show(Request $request, LoyaltyTierService $loyaltyTierService): View
    {
        $user = $request->user();
        $card = $this->cardFor($user);

        $allowedTxTypes = [
            CardTransaction::TYPE_RECHARGE,
            CardTransaction::TYPE_PURCHASE,
            CardTransaction::TYPE_CASHBACK,
            CardTransaction::TYPE_REFUND,
            CardTransaction::TYPE_CASHBACK_REVERSAL,
            CardTransaction::TYPE_TRANSFER_OUT,
            CardTransaction::TYPE_TRANSFER_IN,
            CardTransaction::TYPE_GIFT_CARD_PURCHASE,
            CardTransaction::TYPE_GIFT_CARD_REDEEM,
            CardTransaction::TYPE_GOAL_DEPOSIT,
            CardTransaction::TYPE_GOAL_WITHDRAW,
        ];
        $selectedTxType = in_array($request->input('tx_type'), $allowedTxTypes, true)
            ? (string) $request->input('tx_type')
            : null;

        $transactions = $card->transactions()
            ->when($selectedTxType !== null, fn ($q) => $q->where('type', $selectedTxType))
            ->latest()
            ->paginate(15, ['*'], 'tx_page')
            ->withQueryString();

        $recharges = $card->recharges()
            ->latest()
            ->paginate(10, ['*'], 'rec_page')
            ->withQueryString();

        $pendingRechargesTotal = (float) $card->recharges()
            ->where('status', Recharge::STATUS_PENDING)
            ->sum('amount');

        $activePixCharge = $card->recharges()
            ->where('payment_method', 'pix_dynamic')
            ->where('status', Recharge::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        $activePixQrSvg = $activePixCharge && $activePixCharge->pix_payload
            ? PixQrCodeSvgGenerator::renderSvg($activePixCharge->pix_payload, 176)
            : null;

        $dailySpent = CartPricing::toDecimal($this->ledger->dailySpentCents($card));
        $loyaltySummary = $loyaltyTierService->summaryFor($user);

        $sentGiftCards = GiftCard::query()
            ->where('purchaser_user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        $redeemedGiftCards = GiftCard::query()
            ->where('redeemer_user_id', $user->id)
            ->latest('redeemed_at')
            ->limit(10)
            ->get();

        $savingsGoals = $user->savingsGoals()
            ->with('product')
            ->latest()
            ->get();

        $savingsGoalsTotal = (float) $savingsGoals->sum('current_amount');
        $goalPresetProduct = null;
        if ($request->filled('goal_product')) {
            $goalPresetProduct = Product::where('id', (int) $request->input('goal_product'))
                ->where('is_active', true)
                ->first();
        }

        return view('card.show', compact(
            'card',
            'transactions',
            'recharges',
            'pendingRechargesTotal',
            'activePixCharge',
            'activePixQrSvg',
            'dailySpent',
            'loyaltySummary',
            'sentGiftCards',
            'redeemedGiftCards',
            'savingsGoals',
            'savingsGoalsTotal',
            'goalPresetProduct',
            'selectedTxType',
        ));
    }

    /**
     * Gera uma cobrança PIX simulada com BR Code válido (CRC16-CCITT) e QR Code SVG no servidor.
     */
    public function generatePixCharge(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1.00', 'max:999999.99'],
        ]);

        $card = $this->cardFor($request->user());
        $recharge = $gateway->createPixCharge($card, (float) $validated['amount']);

        return back()->with(
            'success',
            "Cobrança PIX ({$recharge->txid}) gerada! Utilize o QR Code ou código copia e cola para concluir o pagamento."
        );
    }

    /**
     * Endpoint JSON autenticado para polling de status da cobrança PIX a cada 5s (autorizado ao dono do cartão).
     */
    public function checkPixStatus(Request $request, Recharge $recharge): JsonResponse
    {
        $recharge->loadMissing('virtualCard');
        abort_unless((int) $recharge->virtualCard?->user_id === (int) $request->user()->id, 403);

        $card = $recharge->virtualCard;
        $remainingSeconds = $recharge->expires_at
            ? max(0, (int) now()->diffInSeconds($recharge->expires_at, false))
            : 0;

        return response()->json([
            'id' => $recharge->id,
            'txid' => $recharge->txid,
            'status' => $recharge->status,
            'amount' => (float) $recharge->amount,
            'approved_amount' => $recharge->approved_amount ? (float) $recharge->approved_amount : null,
            'card_balance' => (float) $card->balance,
            'card_balance_formatted' => number_format((float) $card->balance, 2, ',', '.'),
            'expires_at_iso' => $recharge->expires_at?->toIso8601String(),
            'remaining_seconds' => $remainingSeconds,
            'is_expired' => $recharge->expires_at ? now()->gt($recharge->expires_at) : false,
        ]);
    }

    /**
     * Endpoint de Webhook PIX Simulado idempotente (disponível apenas fora de produção e restrito ao titular/financeiro).
     */
    public function simulatePixWebhook(Request $request, PaymentGateway $gateway): RedirectResponse|JsonResponse
    {
        abort_if(app()->isProduction(), 403, 'Simulação de webhook PIX desabilitada em produção.');

        $validated = $request->validate([
            'txid' => ['required', 'string', 'max:64', 'exists:recharges,txid'],
        ]);

        $targetRecharge = Recharge::with('virtualCard')
            ->where('txid', $validated['txid'])
            ->firstOrFail();

        $user = $request->user();
        abort_unless(
            $user && ((int) $targetRecharge->virtualCard?->user_id === (int) $user->id || $user->canApproveRecharges()),
            403,
            'Você não tem permissão para confirmar esta cobrança PIX.'
        );

        $recharge = $gateway->confirmPixWebhook($validated['txid'], $user->id);
        $card = $recharge->virtualCard()->first();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $recharge->status,
                'txid' => $recharge->txid,
                'approved_amount' => (float) $recharge->approved_amount,
                'card_balance' => (float) ($card?->balance ?? 0),
                'card_balance_formatted' => number_format((float) ($card?->balance ?? 0), 2, ',', '.'),
                'message' => 'Pagamento PIX confirmado! R$ '.number_format((float) $recharge->approved_amount, 2, ',', '.').' creditados na carteira.',
            ]);
        }

        return back()->with(
            'success',
            'Pagamento PIX confirmado via webhook! R$ '.number_format((float) $recharge->approved_amount, 2, ',', '.').' creditados na carteira.'
        );
    }

    /**
     * Revela o CVV e a numeração completa temporariamente apenas mediante confirmação do PIN (suporta JSON e Redirect).
     */
    public function revealCvv(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        $throttleKey = self::pinThrottleKey($request->user()->id);

        if (RateLimiter::tooManyAttempts($throttleKey, self::PIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            throw ValidationException::withMessages([
                'pin' => "Muitas tentativas incorretas de PIN. Aguarde {$minutes} minuto(s) ({$seconds}s).",
            ]);
        }

        $card = $this->cardFor($request->user());

        if (empty($card->security_pin)) {
            throw ValidationException::withMessages([
                'pin' => 'Defina um PIN de 4 dígitos antes de revelar os dados de segurança do cartão.',
            ]);
        }

        if (! Hash::check((string) $validated['pin'], $card->security_pin)) {
            RateLimiter::hit($throttleKey, self::PIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'pin' => 'PIN incorreto. Não foi possível revelar os dados do cartão.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        AuditLog::record('card.cvv_revealed', $card, null, null, $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'card_number' => $card->formatted_number,
                'cvv' => $card->cvv,
                'expiration_date' => $card->expiration_date,
                'message' => 'Dados do cartão virtual revelados temporariamente.',
            ]);
        }

        return back()->with('success', 'PIN verificado. Utilize a visualização segura na tela do cartão.');
    }

    public function storeRecharge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1.00', 'max:999999.99'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $card = $this->cardFor($request->user());
        $file = $request->file('receipt');
        $receiptHash = hash_file('sha256', $file->getRealPath());

        if (Recharge::where('receipt_hash', $receiptHash)->exists()) {
            throw ValidationException::withMessages([
                'receipt' => 'Este comprovante já foi enviado anteriormente. Comprovantes duplicados não são aceitos.',
            ]);
        }

        $path = $file->store('receipts', 'local');

        $recharge = $card->recharges()->create([
            'payment_method' => 'receipt',
            'amount' => $validated['amount'],
            'receipt_path' => $path,
            'receipt_hash' => $receiptHash,
            'status' => Recharge::STATUS_PENDING,
        ]);

        AuditLog::record(
            'recharge.requested',
            $recharge,
            null,
            ['amount' => (float) $recharge->amount, 'status' => $recharge->status],
            $request->user()->id
        );

        Cache::forget('admin_pending_counts');

        return back()->with('success', 'Comprovante enviado! O saldo será liberado após validação.');
    }

    public function showReceipt(Request $request, Recharge $recharge): StreamedResponse
    {
        $recharge->loadMissing('virtualCard');
        Gate::authorize('viewReceipt', $recharge);

        $path = (string) $recharge->receipt_path;
        abort_if($path === '', 404, 'Esta recarga não possui arquivo de comprovante anexado.');

        $disk = Storage::disk('local')->exists($path)
            ? 'local'
            : (Storage::disk('public')->exists($path) ? 'public' : null);

        abort_if($disk === null, 404, 'Arquivo de comprovante não encontrado.');

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedMimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        $mimeType = $allowedMimes[$extension] ?? (Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream');
        $safeFilename = sprintf('comprovante-recarga-%05d.%s', $recharge->id, $extension ?: 'bin');

        return Storage::disk($disk)->response($path, $safeFilename, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function toggleBlock(Request $request, CardPinGuard $pinGuard): RedirectResponse
    {
        $card = $this->cardFor($request->user());
        $oldActive = (bool) $card->is_active;

        // Ao desbloquear um cartão que já possui PIN configurado, exige validação do PIN
        if (! $oldActive && ! empty($card->security_pin)) {
            $validated = $request->validate([
                'pin' => ['required', 'digits:4'],
            ]);

            $pinGuard->verify(
                user: $request->user(),
                card: $card,
                pin: (string) $validated['pin'],
                field: 'pin',
                invalidPinMessage: 'PIN incorreto. Informe o PIN de 4 dígitos para desbloquear o cartão.',
            );
        }

        $card->update(['is_active' => ! $oldActive]);

        AuditLog::record(
            'card.toggle_block',
            $card,
            ['is_active' => $oldActive],
            ['is_active' => (bool) $card->is_active],
            $request->user()->id
        );

        return back()->with(
            'success',
            $card->is_active
                ? 'Cartão desbloqueado com sucesso.'
                : 'Cartão bloqueado temporariamente. Nenhuma compra poderá ser feita enquanto estiver bloqueado.'
        );
    }

    public function transfer(
        Request $request,
        TransferBalanceAction $transferBalance,
        CardPinGuard $pinGuard,
    ): RedirectResponse {
        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:180'],
            'amount' => ['required', 'numeric', 'min:1.00', 'max:10000.00'],
            'pin' => ['required', 'digits:4'],
            'note' => ['nullable', 'string', 'max:140'],
        ]);

        $sender = $request->user();
        $card = $this->cardFor($sender);

        $pinGuard->verify(
            user: $sender,
            card: $card,
            pin: (string) $validated['pin'],
            field: 'pin',
            missingPinMessage: 'Configure um PIN de 4 dígitos no seu cartão antes de transferir saldo.',
            invalidPinMessage: 'PIN incorreto. Confirme seu PIN de 4 dígitos para concluir a transferência.',
        );

        try {
            $result = $transferBalance->execute(
                sender: $sender,
                recipientEmail: (string) $validated['recipient_email'],
                amount: (float) $validated['amount'],
                note: $validated['note'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $formattedAmount = 'R$ '.number_format((float) $validated['amount'], 2, ',', '.');

        return redirect()->route('card.show')->with(
            'success',
            "Transferência instantânea de {$formattedAmount} enviada para {$result['recipient']->name}!"
        );
    }

    public function updateDailyLimit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'daily_limit' => ['nullable', 'numeric', 'min:10.00', 'max:999999.99'],
        ]);

        $card = $this->cardFor($request->user());
        $oldLimit = $card->daily_limit !== null ? (float) $card->daily_limit : null;
        $newLimit = isset($validated['daily_limit']) && $validated['daily_limit'] !== ''
            ? round((float) $validated['daily_limit'], 2)
            : null;

        $card->update(['daily_limit' => $newLimit]);

        AuditLog::record(
            'card.daily_limit_updated',
            $card,
            ['daily_limit' => $oldLimit],
            ['daily_limit' => $newLimit],
            $request->user()->id
        );

        return back()->with(
            'success',
            $newLimit !== null
                ? 'Limite diário atualizado para R$ '.number_format($newLimit, 2, ',', '.').'.'
                : 'Limite diário removido.'
        );
    }

    /**
     * Rotaciona o código CVV do cartão virtual exigindo confirmação de PIN.
     */
    public function rotateCvv(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        $throttleKey = self::pinThrottleKey($request->user()->id);

        if (RateLimiter::tooManyAttempts($throttleKey, self::PIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            throw ValidationException::withMessages([
                'pin' => "Muitas tentativas incorretas de PIN. Aguarde {$minutes} minuto(s) ({$seconds}s).",
            ]);
        }

        $card = $this->cardFor($request->user());

        if (empty($card->security_pin)) {
            throw ValidationException::withMessages([
                'pin' => 'Defina um PIN de 4 dígitos antes de rotacionar o CVV.',
            ]);
        }

        if (! Hash::check((string) $validated['pin'], $card->security_pin)) {
            RateLimiter::hit($throttleKey, self::PIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'pin' => 'PIN incorreto. Não foi possível rotacionar o CVV.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $previousCvv = (string) $card->cvv;
        do {
            $newCvv = VirtualCard::generateCvv();
        } while ($newCvv === $previousCvv);

        $card->update(['cvv' => $newCvv]);

        AuditLog::record('card.cvv_rotated', $card, null, ['rotated' => true], $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'CVV rotacionado com sucesso.',
            ]);
        }

        return back()->with('success', 'Novo código de segurança (CVV) gerado com sucesso!');
    }

    /**
     * Exporta o extrato financeiro pessoal do titular em CSV com BOM UTF-8 e proteção anti-Formula Injection.
     */
    public function exportStatementCsv(Request $request): StreamedResponse
    {
        $card = $this->cardFor($request->user());
        $query = $card->transactions()->latest();
        $filename = 'extrato-digitalcard-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Tipo', 'Descrição', 'Valor (R$)', 'Saldo Após (R$)'], ';');

            foreach ($query->cursor() as $tx) {
                fputcsv($handle, [
                    $tx->created_at?->format('d/m/Y H:i'),
                    $this->sanitizeCsvCell($tx->type),
                    $this->sanitizeCsvCell($tx->description),
                    number_format((float) $tx->amount, 2, ',', ''),
                    number_format((float) $tx->balance_after, 2, ',', ''),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_pin' => ['nullable', 'digits:4'],
            'pin' => ['required', 'digits:4', 'confirmed'],
        ]);

        $throttleKey = self::pinThrottleKey($request->user()->id);

        if (RateLimiter::tooManyAttempts($throttleKey, self::PIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            throw ValidationException::withMessages([
                'current_pin' => "Muitas tentativas incorretas de PIN. Por segurança, aguarde {$minutes} minuto(s) ({$seconds}s) antes de tentar novamente.",
            ]);
        }

        $card = $this->cardFor($request->user());

        if ($card->security_pin && ! Hash::check((string) ($validated['current_pin'] ?? ''), $card->security_pin)) {
            RateLimiter::hit($throttleKey, self::PIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'current_pin' => 'O PIN atual informado está incorreto.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $hadPin = ! empty($card->security_pin);
        $card->update([
            'security_pin' => Hash::make($validated['pin']),
        ]);

        AuditLog::record(
            'card.pin_updated',
            $card,
            ['had_pin' => $hadPin],
            ['had_pin' => true],
            $request->user()->id
        );

        return back()->with('success', 'PIN do cartão atualizado com sucesso.');
    }

    public function approveRecharge(Request $request, Recharge $recharge): RedirectResponse
    {
        $validated = $request->validate([
            'approved_amount' => ['nullable', 'numeric', 'min:1.00', 'max:999999.99'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $recharge->loadMissing('virtualCard');
        Gate::authorize('review', $recharge);

        DB::transaction(function () use ($request, $recharge, $validated): void {
            $lockedRecharge = Recharge::where('id', $recharge->id)->lockForUpdate()->firstOrFail();

            if ($lockedRecharge->status !== Recharge::STATUS_PENDING) {
                abort(400, 'Esta recarga já foi processada.');
            }

            $card = VirtualCard::where('id', $lockedRecharge->virtual_card_id)->lockForUpdate()->firstOrFail();

            if ((int) $card->user_id === (int) $request->user()->id) {
                abort(403, 'Administradores não podem aprovar recargas do próprio cartão virtual.');
            }

            $approvedCents = CartPricing::toCents((float) ($validated['approved_amount'] ?? $lockedRecharge->amount));
            $approvedAmount = CartPricing::toDecimal($approvedCents);
            $oldBalance = round((float) $card->balance, 2);

            $tx = $this->ledger->credit(
                lockedCard: $card,
                amountCents: $approvedCents,
                type: CardTransaction::TYPE_RECHARGE,
                description: 'Recarga aprovada #REC-'.str_pad((string) $lockedRecharge->id, 5, '0', STR_PAD_LEFT),
                reference: $lockedRecharge,
                idempotencyKey: "recharge:{$lockedRecharge->id}:manual",
            );

            $newBalance = (float) $tx->balance_after;

            $lockedRecharge->update([
                'status' => Recharge::STATUS_APPROVED,
                'approved_amount' => $approvedAmount,
                'admin_notes' => $validated['admin_notes'] ?? $lockedRecharge->admin_notes,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            AuditLog::record(
                'recharge.approved',
                $lockedRecharge,
                [
                    'status' => Recharge::STATUS_PENDING,
                    'requested_amount' => (float) $lockedRecharge->amount,
                    'card_balance' => $oldBalance,
                ],
                [
                    'status' => Recharge::STATUS_APPROVED,
                    'approved_amount' => $approvedAmount,
                    'reviewed_by' => $request->user()->id,
                    'card_balance' => $newBalance,
                ],
                $request->user()->id
            );
        });

        Cache::forget('admin_pending_counts');
        $recharge->fresh('virtualCard.user')?->virtualCard?->user?->notify(
            new RechargeReviewedNotification($recharge->fresh(), true)
        );

        return back()->with('success', 'Recarga aprovada e saldo creditado no cartão virtual!');
    }

    public function rejectRecharge(Request $request, Recharge $recharge): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:500'],
        ]);

        $recharge->loadMissing('virtualCard');
        Gate::authorize('review', $recharge);

        DB::transaction(function () use ($request, $recharge, $validated): void {
            $lockedRecharge = Recharge::where('id', $recharge->id)->lockForUpdate()->firstOrFail();

            if ($lockedRecharge->status !== Recharge::STATUS_PENDING) {
                abort(400, 'Esta recarga já foi processada.');
            }

            $card = VirtualCard::where('id', $lockedRecharge->virtual_card_id)->lockForUpdate()->firstOrFail();

            if ((int) $card->user_id === (int) $request->user()->id) {
                abort(403, 'Administradores não podem recusar recargas do próprio cartão virtual.');
            }

            $lockedRecharge->update([
                'status' => Recharge::STATUS_REJECTED,
                'admin_notes' => $validated['admin_notes'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            AuditLog::record(
                'recharge.rejected',
                $lockedRecharge,
                ['status' => Recharge::STATUS_PENDING],
                [
                    'status' => Recharge::STATUS_REJECTED,
                    'admin_notes' => $validated['admin_notes'],
                    'reviewed_by' => $request->user()->id,
                ],
                $request->user()->id
            );
        });

        Cache::forget('admin_pending_counts');
        $recharge->fresh('virtualCard.user')?->virtualCard?->user?->notify(
            new RechargeReviewedNotification($recharge->fresh(), false)
        );

        return back()->with('success', 'Recarga recusada.');
    }

    public static function pinThrottleKey(int $userId): string
    {
        return 'card-pin:'.$userId;
    }

    private function sanitizeCsvCell(?string $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }

    private function cardFor(User $user): VirtualCard
    {
        return $user->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
        ]);
    }
}
