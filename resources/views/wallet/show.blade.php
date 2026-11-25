<x-app-layout :metaTitle="'Carteira de Créditos — ' . $company->trade_name" :metaDescription="'Consulte o saldo em créditos pré-pagos, contrate planos NexoSupri e acompanhe o extrato imutável da empresa.'">
    @php
        $typeLabels = [
            'credit_purchase' => ['Compra de Créditos', 'bg-money-50 text-money-700 border-money-200'],
            'order_debit' => ['Débito Pedido', 'bg-danger-50 text-danger-700 border-danger-200'],
            'order_refund' => ['Estorno Pedido', 'bg-paper text-slate-700 border-line'],
            'order_return_refund' => ['Estorno Devolução', 'bg-paper text-slate-700 border-line'],
            'manual_adjustment' => ['Ajuste Auditado', 'bg-accent-50 text-accent-800 border-accent-200'],
        ];
        $preselectedSlug = request('plan');
    @endphp

    <div class="app-shell py-8 space-y-8"
         x-data="{
            copied: false,
            toastMessage: 'Copiado!',
            selectedPlanId: {{ ($plans->firstWhere('slug', $preselectedSlug) ?? $plans->firstWhere('slug', 'business') ?? $plans->first())?->id ?? 'null' }},
            pixRemainingSeconds: {{ $activePixCharge && $activePixCharge->expires_at ? max(0, (int) now()->diffInSeconds($activePixCharge->expires_at, false)) : 0 }},
            pixStatus: @js($activePixCharge->status ?? ''),
            init() {
                @if($activePixCharge)
                    setInterval(() => {
                        if (this.pixRemainingSeconds > 0 && this.pixStatus === 'pendente') {
                            this.pixRemainingSeconds--;
                        }
                    }, 1000);

                    const pollTimer = setInterval(async () => {
                        if (this.pixStatus !== 'pendente') {
                            clearInterval(pollTimer);
                            return;
                        }
                        try {
                            const res = await fetch(@js(route('wallet.pix.status', $activePixCharge)), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (!res.ok) return;
                            const data = await res.json();
                            this.pixRemainingSeconds = data.remaining_seconds ?? this.pixRemainingSeconds;
                            if (data.status === 'aprovado') {
                                this.pixStatus = 'aprovado';
                                this.showToast('Pagamento PIX confirmado! Créditos liberados.');
                                clearInterval(pollTimer);
                                setTimeout(() => window.location.reload(), 1200);
                            }
                        } catch (e) {}
                    }, 5000);
                @endif
            },
            formatCountdown(sec) {
                const m = String(Math.floor(sec / 60)).padStart(2, '0');
                const s = String(sec % 60).padStart(2, '0');
                return m + 'm : ' + s + 's';
            },
            showToast(msg) {
                this.toastMessage = msg;
                this.copied = true;
                setTimeout(() => this.copied = false, 3500);
            },
            copyText(text, label = 'Copiado para a área de transferência!') {
                navigator.clipboard?.writeText(text);
                this.showToast(label);
            }
         }">

        <div x-show="copied" x-cloak x-transition role="status" aria-live="polite"
             class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-lg border border-slate-800 bg-ink px-4 py-3 text-xs font-medium text-paper shadow-sm">
            <x-icon name="check" class="size-4 text-money-400" />
            <span x-text="toastMessage"></span>
        </div>

        <div class="flex flex-col justify-between gap-4 border-b border-line pb-5 md:flex-row md:items-end">
            <div>
                <x-eyebrow>Modelo A · Crédito exclusivo da empresa</x-eyebrow>
                <h1 class="mt-1 font-serif text-3xl text-ink">Carteira de Créditos — {{ $company->trade_name }}</h1>
                <p class="text-xs text-slate-600 mt-1">Conversão oficial: <strong>R$ 1,00 = 1 crédito</strong> (uso exclusivo no catálogo NexoSupri, sem saque ou transferência entre empresas).</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium {{ $wallet->is_active ? 'border-money-200 bg-money-50 text-money-800' : 'border-danger-200 bg-danger-50 text-danger-800' }}">
                    <span class="size-2 rounded-full {{ $wallet->is_active ? 'bg-money-500' : 'bg-danger-500' }}"></span>
                    {{ $wallet->is_active ? 'Carteira ativa' : 'Carteira bloqueada' }}
                </span>
                <a href="{{ route('catalog.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-ink hover:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-paper transition">
                    <span>Acessar catálogo</span>
                    <x-icon name="arrow-right" class="size-4" />
                </a>
            </div>
        </div>

        @if($isLowBalance)
            <div class="rounded-xl border border-accent-200 bg-accent-50 p-4 text-xs text-accent-950 flex items-center justify-between gap-3">
                <span><strong>Saldo baixo:</strong> restam {{ number_format((float) $wallet->balance, 2, ',', '.') }} créditos. Contrate um plano abaixo para manter o abastecimento contínuo.</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
            <div class="lg:col-span-5 max-w-[440px] w-full mx-auto lg:mx-0">
                <x-virtual-card :company="$company" :wallet="$wallet" :planName="$activeContract?->plan?->name" :interactive="true" />
            </div>

            <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-ink text-paper rounded-xl p-5 space-y-1">
                    <span class="text-xs text-slate-400">Saldo disponível</span>
                    <p class="font-mono text-2xl font-bold text-money-400 tabular-nums">{{ number_format((float) $wallet->balance, 2, ',', '.') }} cr.</p>
                    <span class="text-[11px] text-slate-300">Poder de compra: R$ {{ number_format((float) $wallet->balance, 2, ',', '.') }}</span>
                </div>
                <div class="bg-surface rounded-xl border border-line p-5 space-y-1">
                    <span class="text-xs text-slate-500">Créditos contratados</span>
                    <p class="font-mono text-2xl font-bold text-ink tabular-nums">{{ number_format((float) $wallet->total_acquired, 2, ',', '.') }} cr.</p>
                    <span class="text-[11px] text-slate-500">Conversão exata 1:1 (sem bônus ou deságio)</span>
                </div>
                <div class="bg-surface rounded-xl border border-line p-5 space-y-1">
                    <span class="text-xs text-slate-500">Créditos utilizados</span>
                    <p class="font-mono text-2xl font-bold text-ink tabular-nums">{{ number_format((float) $wallet->total_spent, 2, ',', '.') }} cr.</p>
                    <span class="text-[11px] text-slate-500">Consumido hoje: {{ number_format($dailySpent, 2, ',', '.') }} cr.</span>
                </div>
                <div class="bg-surface rounded-xl border border-line p-5 space-y-1">
                    <span class="text-xs text-slate-500">Pagamentos pendentes</span>
                    <p class="font-mono text-2xl font-bold text-accent-700 tabular-nums">R$ {{ number_format($pendingPaymentsTotal, 2, ',', '.') }}</p>
                    <span class="text-[11px] text-slate-500">{{ $activeContract ? 'Contrato: '.$activeContract->plan?->name : 'Sem contrato ativo' }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <section class="lg:col-span-7 bg-surface rounded-xl border border-line p-6 space-y-5">
                <div>
                    <x-eyebrow>Nova contratação de créditos</x-eyebrow>
                    <h2 class="font-serif text-2xl text-ink mt-0.5">Escolher Plano e Gerar Pagamento</h2>
                    <p class="text-xs text-slate-600 mt-0.5">Pague via PIX instantâneo ou envie comprovante bancário para liberação 1:1.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                    @foreach($plans as $plan)
                        <button type="button"
                                @click="selectedPlanId = {{ $plan->id }}"
                                :class="selectedPlanId === {{ $plan->id }} ? 'border-ink bg-paper ring-1 ring-ink' : 'border-line bg-surface hover:border-slate-400'"
                                class="text-left rounded-lg border p-3.5 transition {{ $plan->is_custom_amount ? 'sm:col-span-2' : '' }}">
                            <div class="flex items-center justify-between">
                                <strong class="text-sm text-ink">{{ $plan->name }}</strong>
                                <span class="font-mono font-bold text-ink">R$ {{ number_format((float) $plan->price, 0, ',', '.') }}{{ $plan->is_custom_amount ? '+' : '' }}</span>
                            </div>
                            <span class="text-[11px] text-slate-500 block mt-0.5">{{ $plan->target_profile }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-line">
                    <form action="{{ route('wallet.plans.pix') }}" method="POST" class="rounded-xl border border-line bg-paper p-4 space-y-3 text-xs">
                        @csrf
                        <input type="hidden" name="plan_id" :value="selectedPlanId">
                        <h3 class="font-semibold text-ink flex items-center gap-1.5">
                            <x-icon name="qr-code" class="size-4 text-money-600" />
                            <span>Pagar via PIX</span>
                        </h3>

                        <div>
                            <label class="block text-slate-600 mb-1">Valor personalizado (somente Enterprise ≥ R$ 10.000)</label>
                            <input type="number" name="custom_amount" step="0.01" min="500" placeholder="Opcional"
                                   class="w-full rounded-md border-line bg-surface font-mono text-xs py-2 px-3">
                        </div>

                        <label class="flex items-start gap-2 text-[11px] text-slate-700 cursor-pointer">
                            <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5 rounded border-line">
                            <span>Aceito os termos do Modelo A: créditos exclusivos para compras na NexoSupri, sem saque ou transferência entre empresas.</span>
                        </label>

                        <button type="submit" class="w-full rounded-lg bg-ink hover:bg-slate-800 text-paper font-semibold py-2.5 transition">
                            Gerar QR Code PIX
                        </button>
                    </form>

                    <form action="{{ route('wallet.plans.receipt') }}" method="POST" enctype="multipart/form-data" class="rounded-xl border border-line bg-paper p-4 space-y-3 text-xs">
                        @csrf
                        <input type="hidden" name="plan_id" :value="selectedPlanId">
                        <h3 class="font-semibold text-ink flex items-center gap-1.5">
                            <x-icon name="file-text" class="size-4 text-accent-600" />
                            <span>Enviar Comprovante</span>
                        </h3>

                        <div>
                            <label class="block text-slate-600 mb-1">Comprovante (PDF, JPG ou PNG)</label>
                            <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" required
                                   class="w-full text-[11px] text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-ink file:text-paper">
                        </div>

                        <label class="flex items-start gap-2 text-[11px] text-slate-700 cursor-pointer">
                            <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5 rounded border-line">
                            <span>Aceito o contrato do Modelo A (1 crédito = R$ 1,00).</span>
                        </label>

                        <button type="submit" class="w-full rounded-lg border border-ink bg-surface hover:bg-ink hover:text-paper text-ink font-semibold py-2.5 transition">
                            Enviar para validação financeira
                        </button>
                    </form>
                </div>

                @if($activePixCharge)
                    <div class="rounded-xl border border-money-200 bg-money-50/50 p-5 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="text-xs font-semibold text-money-800 block">Cobrança PIX pendente ({{ $activePixCharge->txid }})</span>
                                <span class="font-mono text-lg font-bold text-ink tabular-nums">
                                    R$ {{ number_format((float) $activePixCharge->amount, 2, ',', '.') }} = {{ number_format((float) $activePixCharge->amount, 2, ',', '.') }} créditos
                                </span>
                            </div>
                            <span class="font-mono text-xs px-2.5 py-1 rounded bg-surface border border-line" x-text="'Expira em ' + formatCountdown(pixRemainingSeconds)"></span>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-5">
                            @if($activePixQrSvg)
                                <div class="p-3 bg-white rounded-lg border border-line shrink-0">{!! $activePixQrSvg !!}</div>
                            @endif
                            <div class="flex-1 min-w-0 space-y-2 text-xs w-full">
                                <p class="text-slate-700 font-medium">Código PIX Copia e Cola:</p>
                                <div class="flex gap-2">
                                    <input type="text" readonly value="{{ $activePixCharge->pix_payload }}"
                                           class="flex-1 min-w-0 rounded-md border-line bg-surface font-mono text-[11px] py-2 px-2.5">
                                    <button type="button" @click="copyText(@js($activePixCharge->pix_payload), 'Código PIX copiado!')"
                                            class="px-3 py-2 rounded-md bg-ink text-paper font-semibold shrink-0">
                                        Copiar
                                    </button>
                                </div>

                                @if(! app()->isProduction())
                                    <form action="{{ route('webhooks.pix.simulate') }}" method="POST" class="pt-1">
                                        @csrf
                                        <input type="hidden" name="txid" value="{{ $activePixCharge->txid }}">
                                        <button type="submit" class="rounded-md bg-money-600 hover:bg-money-700 text-paper font-semibold px-4 py-2 transition">
                                            Simular Confirmação PIX (Ambiente Local)
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <section class="lg:col-span-5 space-y-6">
                @if($isManager)
                    <div class="bg-surface rounded-xl border border-line p-6 space-y-4 text-xs">
                        <h2 class="font-serif text-xl text-ink">Controles de Segurança da Carteira</h2>

                        <form action="{{ route('wallet.toggle-block') }}" method="POST" class="flex items-center justify-between gap-3 p-3 rounded-lg bg-paper border border-line">
                            @csrf
                            @method('PATCH')
                            <div>
                                <strong class="block text-ink">{{ $wallet->is_active ? 'Carteira liberada para compras' : 'Carteira bloqueada' }}</strong>
                                <span class="text-slate-500">Bloqueio preventivo impede novos débitos.</span>
                            </div>
                            <button type="submit" class="px-3.5 py-2 rounded-md border font-semibold {{ $wallet->is_active ? 'border-danger-200 text-danger-700 bg-danger-50' : 'border-money-200 text-money-700 bg-money-50' }}">
                                {{ $wallet->is_active ? 'Bloquear' : 'Desbloquear' }}
                            </button>
                        </form>

                        <form action="{{ route('wallet.update-limit') }}" method="POST" class="space-y-2">
                            @csrf
                            @method('PATCH')
                            <label class="block font-medium text-slate-700">Limite diário de consumo (créditos)</label>
                            <div class="flex gap-2">
                                <input type="number" name="daily_limit" step="0.01" min="50" value="{{ $wallet->daily_limit }}"
                                       placeholder="Sem limite diário" class="flex-1 rounded-md border-line font-mono py-2 px-3">
                                <button type="submit" class="rounded-md bg-ink text-paper px-4 py-2 font-semibold">Salvar</button>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="bg-surface rounded-xl border border-line p-6 space-y-3 text-xs">
                    <h2 class="font-serif text-xl text-ink">Pagamentos Recentes</h2>
                    <div class="divide-y divide-line">
                        @forelse($payments as $pay)
                            <div class="py-2.5 flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-ink">
                                        {{ $pay->contract?->plan?->name ?? 'Créditos NexoSupri' }}
                                        <span class="font-mono text-slate-500">· R$ {{ number_format((float) $pay->amount, 2, ',', '.') }}</span>
                                    </p>
                                    <p class="text-[11px] text-slate-500">{{ $pay->created_at->format('d/m/Y H:i') }} · {{ $pay->payment_method === 'pix_dynamic' ? 'PIX' : 'Comprovante' }}</p>
                                </div>
                                <span class="px-2 py-0.5 rounded border text-[11px] font-semibold {{ $pay->status === 'aprovado' ? 'border-money-200 bg-money-50 text-money-700' : ($pay->status === 'recusado' ? 'border-danger-200 bg-danger-50 text-danger-700' : 'border-accent-200 bg-accent-50 text-accent-900') }}">
                                    {{ $pay->status }}
                                </span>
                            </div>
                        @empty
                            <p class="py-4 text-slate-500">Nenhum pagamento registrado.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <section class="bg-surface rounded-xl border border-line overflow-hidden shadow-sm">
            <div class="p-6 border-b border-line flex flex-wrap items-center justify-between gap-4">
                <div>
                    <x-eyebrow>Auditoria financeira</x-eyebrow>
                    <h2 class="font-serif text-2xl text-ink mt-0.5">Extrato Imutável de Créditos</h2>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <form action="{{ route('wallet.show') }}" method="GET" class="flex items-center gap-2">
                        <select name="tx_type" onchange="this.form.submit()" class="rounded-md border-line py-1.5 px-3 text-xs">
                            <option value="">Todos os lançamentos</option>
                            @foreach($typeLabels as $k => [$lbl, $cls])
                                <option value="{{ $k }}" {{ $selectedTxType === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </form>

                    <a href="{{ route('wallet.statement.csv') }}"
                       class="inline-flex items-center gap-1.5 rounded-md border border-line bg-paper hover:border-ink px-3.5 py-1.5 font-semibold text-ink transition">
                        <x-icon name="receipt" class="size-3.5" />
                        <span>Exportar extrato CSV</span>
                    </a>
                </div>
            </div>

            <div class="table-scroll-region">
                <table class="w-full min-w-[760px] text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-line bg-paper text-slate-500 font-medium">
                            <th class="py-3 px-5">Data</th>
                            <th class="py-3 px-5">Tipo</th>
                            <th class="py-3 px-5">Responsável</th>
                            <th class="py-3 px-5">Descrição &amp; Motivo</th>
                            <th class="py-3 px-5 text-right">Créditos</th>
                            <th class="py-3 px-5 text-right">Saldo Anterior</th>
                            <th class="py-3 px-5 text-right">Saldo Posterior</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse($transactions as $tx)
                            @php([$tLabel, $tClass] = $typeLabels[$tx->type] ?? [$tx->type, 'bg-paper text-slate-700 border-line'])
                            <tr class="hover:bg-paper/40">
                                <td class="py-3.5 px-5 font-mono text-slate-600 whitespace-nowrap">{{ $tx->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="py-3.5 px-5">
                                    <span class="inline-block px-2 py-0.5 rounded border text-[11px] font-medium {{ $tClass }}">{{ $tLabel }}</span>
                                </td>
                                <td class="py-3.5 px-5 text-slate-700">{{ $tx->user?->name ?? 'Sistema' }}</td>
                                <td class="py-3.5 px-5">
                                    <p class="font-medium text-ink">{{ $tx->description }}</p>
                                    @if($tx->reason)
                                        <p class="text-[11px] text-slate-500">{{ $tx->reason }}</p>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right font-mono font-semibold tabular-nums {{ (float) $tx->amount >= 0 ? 'text-money-700' : 'text-danger-700' }}">
                                    {{ (float) $tx->amount >= 0 ? '+' : '' }}{{ number_format((float) $tx->amount, 2, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-5 text-right font-mono tabular-nums text-slate-500">{{ number_format((float) $tx->balance_before, 2, ',', '.') }}</td>
                                <td class="py-3.5 px-5 text-right font-mono tabular-nums font-semibold text-ink">{{ number_format((float) $tx->balance_after, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-500">Nenhuma movimentação registrada ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-line">
                {{ $transactions->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
