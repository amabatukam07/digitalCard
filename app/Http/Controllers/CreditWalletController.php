<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\PaymentReviewedNotification;
use App\Services\CartPricing;
use App\Services\Payment\PixQrCodeSvgGenerator;
use App\Services\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CreditWalletController extends Controller
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->primaryCompany() === null && $user->hasAdminAccess()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('warning', 'A carteira de créditos pertence às empresas clientes. Gerencie pagamentos e saldos pelo Backoffice.');
        }

        [$company, $wallet] = $this->resolveCompanyAndWallet($user);

        $allowedTxTypes = [
            CreditTransaction::TYPE_CREDIT_PURCHASE,
            CreditTransaction::TYPE_ORDER_DEBIT,
            CreditTransaction::TYPE_ORDER_REFUND,
            CreditTransaction::TYPE_ORDER_RETURN_REFUND,
            CreditTransaction::TYPE_MANUAL_ADJUSTMENT,
        ];
        $selectedTxType = in_array($request->input('tx_type'), $allowedTxTypes, true)
            ? (string) $request->input('tx_type')
            : null;

        $transactions = $wallet->transactions()
            ->with('user')
            ->when($selectedTxType !== null, fn ($q) => $q->where('type', $selectedTxType))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15, ['*'], 'tx_page')
            ->withQueryString();

        $payments = $wallet->payments()
            ->with(['contract.plan', 'requestedBy'])
            ->latest()
            ->paginate(10, ['*'], 'pag_page')
            ->withQueryString();

        $pendingPaymentsTotal = (float) $wallet->payments()
            ->where('status', Payment::STATUS_PENDING)
            ->sum('amount');

        $activePixCharge = $wallet->payments()
            ->where('payment_method', 'pix_dynamic')
            ->where('status', Payment::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        $activePixQrSvg = $activePixCharge && $activePixCharge->pix_payload
            ? PixQrCodeSvgGenerator::renderSvg($activePixCharge->pix_payload, 176)
            : null;

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $activeContract = $company->activeContract();
        $dailySpent = CartPricing::toDecimal($this->ledger->dailySpentCents($wallet));
        $isManager = $user->isCompanyManager($company);
        $isLowBalance = (float) $wallet->balance <= (float) $company->low_balance_threshold;

        return view('wallet.show', compact(
            'company',
            'wallet',
            'transactions',
            'payments',
            'pendingPaymentsTotal',
            'activePixCharge',
            'activePixQrSvg',
            'plans',
            'activeContract',
            'dailySpent',
            'isManager',
            'isLowBalance',
            'selectedTxType',
        ));
    }

    /**
     * Contrata plano (ou carga Enterprise) gerando cobrança PIX 1:1 com aceite do Modelo A.
     */
    public function hirePlanWithPix(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $user = $request->user();
        [$company, $wallet] = $this->resolveCompanyAndWallet($user);

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'custom_amount' => ['nullable', 'numeric', 'min:500.00', 'max:999999.99'],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'É obrigatório aceitar os termos do Modelo A (crédito exclusivo sem saque ou transferência).',
        ]);

        $plan = Plan::findOrFail((int) $validated['plan_id']);
        $amount = $this->resolveContractAmount($plan, $validated['custom_amount'] ?? null);

        $contract = Contract::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'contracted_amount' => $amount,
            'credits_granted' => $amount,
            'status' => Contract::STATUS_PENDING_PAYMENT,
            'terms_version' => (string) config('nexosupri.terms_version', 'Modelo-A-v1'),
            'accepted_terms_at' => now(),
            'accepted_by_user_id' => $user->id,
        ]);

        $payment = $gateway->createPixCharge($company, $wallet, $amount, $contract, (int) $user->id);

        return back()->with(
            'success',
            "Cobrança PIX ({$payment->txid}) gerada para o plano {$plan->name} — R$ ".number_format($amount, 2, ',', '.').' = '.number_format($amount, 0, ',', '.').' créditos.'
        );
    }

    /**
     * Contrata plano enviando comprovante bancário privado para análise do Financeiro.
     */
    public function hirePlanWithReceipt(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$company, $wallet] = $this->resolveCompanyAndWallet($user);

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'custom_amount' => ['nullable', 'numeric', 'min:500.00', 'max:999999.99'],
            'accept_terms' => ['accepted'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'accept_terms.accepted' => 'É obrigatório aceitar os termos do Modelo A antes de contratar créditos.',
        ]);

        $plan = Plan::findOrFail((int) $validated['plan_id']);
        $amount = $this->resolveContractAmount($plan, $validated['custom_amount'] ?? null);

        $file = $request->file('receipt');
        $receiptHash = hash_file('sha256', $file->getRealPath());

        if (Payment::where('receipt_hash', $receiptHash)->exists()) {
            throw ValidationException::withMessages([
                'receipt' => 'Este comprovante já foi enviado anteriormente.',
            ]);
        }

        $path = $file->store('receipts', 'local');

        $payment = DB::transaction(function () use ($company, $wallet, $plan, $amount, $user, $path, $receiptHash): Payment {
            $contract = Contract::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'contracted_amount' => $amount,
                'credits_granted' => $amount,
                'status' => Contract::STATUS_PENDING_PAYMENT,
                'terms_version' => (string) config('nexosupri.terms_version', 'Modelo-A-v1'),
                'accepted_terms_at' => now(),
                'accepted_by_user_id' => $user->id,
            ]);

            return $wallet->payments()->create([
                'company_id' => $company->id,
                'contract_id' => $contract->id,
                'requested_by_user_id' => $user->id,
                'payment_method' => 'receipt',
                'amount' => $amount,
                'receipt_path' => $path,
                'receipt_hash' => $receiptHash,
                'status' => Payment::STATUS_PENDING,
            ]);
        });

        AuditLog::record(
            'payment.receipt_submitted',
            $payment,
            null,
            ['amount' => $amount, 'plan' => $plan->slug, 'status' => $payment->status],
            $user->id,
            $company->id
        );

        Cache::forget('admin_pending_counts');

        return back()->with('success', 'Comprovante enviado! Os créditos (1:1) serão liberados após conferência do Financeiro.');
    }

    public function checkPixStatus(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->company_id !== null && $request->user()->belongsToCompany((int) $payment->company_id), 403);

        $wallet = $payment->wallet()->firstOrFail();
        $remainingSeconds = $payment->expires_at
            ? max(0, (int) now()->diffInSeconds($payment->expires_at, false))
            : 0;

        return response()->json([
            'id' => $payment->id,
            'txid' => $payment->txid,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'approved_amount' => $payment->approved_amount ? (float) $payment->approved_amount : null,
            'wallet_balance' => (float) $wallet->balance,
            'wallet_balance_formatted' => number_format((float) $wallet->balance, 2, ',', '.'),
            'expires_at_iso' => $payment->expires_at?->toIso8601String(),
            'remaining_seconds' => $remainingSeconds,
            'is_expired' => $payment->expires_at ? now()->gt($payment->expires_at) : false,
        ]);
    }

    public function simulatePixWebhook(Request $request, PaymentGateway $gateway): RedirectResponse|JsonResponse
    {
        abort_if(app()->isProduction(), 403, 'Simulação de webhook PIX desabilitada em produção.');

        $validated = $request->validate([
            'txid' => ['required', 'string', 'max:64', 'exists:payments,txid'],
        ]);

        $targetPayment = Payment::where('txid', $validated['txid'])->firstOrFail();
        $user = $request->user();

        abort_unless(
            $user && ($user->belongsToCompany((int) $targetPayment->company_id) || $user->canManageFinance()),
            403,
            'Você não tem permissão para confirmar esta cobrança PIX.'
        );

        $payment = $gateway->confirmPixWebhook($validated['txid'], $user->id);
        $wallet = $payment->wallet()->firstOrFail();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $payment->status,
                'txid' => $payment->txid,
                'approved_amount' => (float) $payment->approved_amount,
                'wallet_balance' => (float) $wallet->balance,
                'wallet_balance_formatted' => number_format((float) $wallet->balance, 2, ',', '.'),
                'message' => 'Pagamento PIX confirmado! '.number_format((float) $payment->approved_amount, 2, ',', '.').' créditos liberados.',
            ]);
        }

        return back()->with(
            'success',
            'Pagamento PIX confirmado! '.number_format((float) $payment->approved_amount, 2, ',', '.').' créditos liberados na carteira da empresa.'
        );
    }

    public function showReceipt(Request $request, Payment $payment): StreamedResponse
    {
        Gate::authorize('viewReceipt', $payment);

        $path = (string) $payment->receipt_path;
        abort_if($path === '', 404, 'Este pagamento não possui arquivo de comprovante anexado.');

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
        $safeFilename = sprintf('comprovante-nexosupri-%05d.%s', $payment->id, $extension ?: 'bin');

        return Storage::disk($disk)->response($path, $safeFilename, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function toggleBlock(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$company, $wallet] = $this->resolveCompanyAndWallet($user);
        Gate::authorize('manageTeam', $company);

        $oldActive = (bool) $wallet->is_active;
        $wallet->update(['is_active' => ! $oldActive]);

        AuditLog::record(
            'wallet.toggle_block',
            $wallet,
            ['is_active' => $oldActive],
            ['is_active' => (bool) $wallet->is_active],
            $user->id,
            $company->id
        );

        return back()->with(
            'success',
            $wallet->is_active
                ? 'Carteira de créditos reativada para pedidos.'
                : 'Carteira de créditos bloqueada temporariamente.'
        );
    }

    public function updateDailyLimit(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$company, $wallet] = $this->resolveCompanyAndWallet($user);
        Gate::authorize('manageTeam', $company);

        $validated = $request->validate([
            'daily_limit' => ['nullable', 'numeric', 'min:50.00', 'max:999999.99'],
        ]);

        $oldLimit = $wallet->daily_limit !== null ? (float) $wallet->daily_limit : null;
        $newLimit = isset($validated['daily_limit']) && $validated['daily_limit'] !== ''
            ? round((float) $validated['daily_limit'], 2)
            : null;

        $wallet->update(['daily_limit' => $newLimit]);

        AuditLog::record(
            'wallet.daily_limit_updated',
            $wallet,
            ['daily_limit' => $oldLimit],
            ['daily_limit' => $newLimit],
            $user->id,
            $company->id
        );

        return back()->with(
            'success',
            $newLimit !== null
                ? 'Limite diário de consumo atualizado para '.number_format($newLimit, 2, ',', '.').' créditos.'
                : 'Limite diário de consumo removido.'
        );
    }

    public function exportStatementCsv(Request $request): StreamedResponse
    {
        [$company, $wallet] = $this->resolveCompanyAndWallet($request->user());
        $query = $wallet->transactions()->with('user')->latest('created_at')->latest('id');
        $filename = 'extrato-creditos-nexosupri-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Tipo', 'Usuário', 'Descrição', 'Motivo', 'Créditos', 'Saldo Anterior', 'Saldo Após'], ';');

            foreach ($query->cursor() as $tx) {
                fputcsv($handle, [
                    $tx->created_at?->format('d/m/Y H:i'),
                    $this->sanitizeCsvCell($tx->type),
                    $this->sanitizeCsvCell($tx->user?->name ?? 'Sistema'),
                    $this->sanitizeCsvCell($tx->description),
                    $this->sanitizeCsvCell($tx->reason ?? ''),
                    number_format((float) $tx->amount, 2, ',', ''),
                    number_format((float) $tx->balance_before, 2, ',', ''),
                    number_format((float) $tx->balance_after, 2, ',', ''),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function approvePayment(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'approved_amount' => ['nullable', 'numeric', 'min:1.00', 'max:999999.99'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        Gate::authorize('review', $payment);

        DB::transaction(function () use ($request, $payment, $validated): void {
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING) {
                abort(400, 'Este pagamento já foi processado.');
            }

            if ((int) $lockedPayment->requested_by_user_id === (int) $request->user()->id) {
                abort(403, 'Operadores não podem aprovar pagamentos solicitados por si mesmos.');
            }

            $wallet = CreditWallet::where('id', $lockedPayment->credit_wallet_id)->lockForUpdate()->firstOrFail();
            $approvedCents = CartPricing::toCents((float) ($validated['approved_amount'] ?? $lockedPayment->amount));
            $approvedAmount = CartPricing::toDecimal($approvedCents);
            $oldBalance = round((float) $wallet->balance, 2);

            $tx = $this->ledger->credit(
                lockedWallet: $wallet,
                amountCents: $approvedCents,
                type: CreditTransaction::TYPE_CREDIT_PURCHASE,
                description: 'Pagamento aprovado #PAG-'.str_pad((string) $lockedPayment->id, 5, '0', STR_PAD_LEFT),
                reference: $lockedPayment,
                idempotencyKey: "payment:{$lockedPayment->id}:manual",
                actorUserId: (int) $request->user()->id,
                reason: $validated['admin_notes'] ?? 'Aprovação financeira (1 crédito = R$ 1,00)',
            );

            $lockedPayment->update([
                'status' => Payment::STATUS_APPROVED,
                'approved_amount' => $approvedAmount,
                'admin_notes' => $validated['admin_notes'] ?? $lockedPayment->admin_notes,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            if ($lockedPayment->contract_id) {
                $contract = Contract::where('id', $lockedPayment->contract_id)->lockForUpdate()->first();
                if ($contract && $contract->status === Contract::STATUS_PENDING_PAYMENT) {
                    $contract->update([
                        'status' => Contract::STATUS_ACTIVE,
                        'contracted_amount' => $approvedAmount,
                        'credits_granted' => $approvedAmount,
                        'starts_at' => now(),
                    ]);
                }
            }

            AuditLog::record(
                'payment.approved',
                $lockedPayment,
                ['status' => Payment::STATUS_PENDING, 'wallet_balance' => $oldBalance],
                ['status' => Payment::STATUS_APPROVED, 'approved_amount' => $approvedAmount, 'wallet_balance' => (float) $tx->balance_after],
                $request->user()->id,
                $lockedPayment->company_id
            );
        });

        Cache::forget('admin_pending_counts');
        $freshPayment = $payment->fresh(['requestedBy']);
        $freshPayment?->requestedBy?->notify(new PaymentReviewedNotification($freshPayment, true));

        return back()->with('success', 'Pagamento aprovado e créditos liberados (1:1) na carteira da empresa!');
    }

    public function rejectPayment(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:500'],
        ]);

        Gate::authorize('review', $payment);

        DB::transaction(function () use ($request, $payment, $validated): void {
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING) {
                abort(400, 'Este pagamento já foi processado.');
            }

            if ((int) $lockedPayment->requested_by_user_id === (int) $request->user()->id) {
                abort(403, 'Operadores não podem recusar pagamentos solicitados por si mesmos.');
            }

            $lockedPayment->update([
                'status' => Payment::STATUS_REJECTED,
                'admin_notes' => $validated['admin_notes'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            if ($lockedPayment->contract_id) {
                $contract = Contract::where('id', $lockedPayment->contract_id)->lockForUpdate()->first();
                if ($contract && $contract->status === Contract::STATUS_PENDING_PAYMENT) {
                    $contract->update(['status' => Contract::STATUS_CANCELLED]);
                }
            }

            AuditLog::record(
                'payment.rejected',
                $lockedPayment,
                ['status' => Payment::STATUS_PENDING],
                ['status' => Payment::STATUS_REJECTED, 'admin_notes' => $validated['admin_notes']],
                $request->user()->id,
                $lockedPayment->company_id
            );
        });

        Cache::forget('admin_pending_counts');
        $freshPayment = $payment->fresh(['requestedBy']);
        $freshPayment?->requestedBy?->notify(new PaymentReviewedNotification($freshPayment, false));

        return back()->with('success', 'Pagamento recusado.');
    }

    private function resolveContractAmount(Plan $plan, mixed $customAmount): float
    {
        if (! $plan->is_custom_amount) {
            return round((float) $plan->price, 2);
        }

        $min = (float) ($plan->min_amount ?: $plan->price);
        $amount = $customAmount !== null && $customAmount !== '' ? round((float) $customAmount, 2) : $min;

        if ($amount < $min) {
            throw ValidationException::withMessages([
                'custom_amount' => 'O plano '.$plan->name.' exige contratação mínima de R$ '.number_format($min, 2, ',', '.').'.',
            ]);
        }

        return $amount;
    }

    /**
     * @return array{0: Company, 1: CreditWallet}
     */
    private function resolveCompanyAndWallet(User $user): array
    {
        $company = $user->primaryCompany();
        abort_unless($company !== null, 403, 'Conta sem empresa associada.');

        $wallet = $company->wallet()->firstOrCreate([], [
            'balance' => 0.00,
            'reserved_balance' => 0.00,
            'total_acquired' => 0.00,
            'total_spent' => 0.00,
            'is_active' => true,
        ]);

        return [$company, $wallet];
    }

    private function sanitizeCsvCell(?string $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }
}
