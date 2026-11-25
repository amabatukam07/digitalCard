@php
    $currency = static fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $canManageFinance = (bool) auth()->user()?->canManageFinance();
@endphp

<x-admin-layout title="Pagamentos & Créditos">
    <div x-data="{
            rejectModal: false,
            rejectAction: '',
            rejectCompany: '',
            openReject(action, companyName) {
                this.rejectAction = action;
                this.rejectCompany = companyName;
                this.rejectModal = true;
            }
         }"
         @keydown.escape.window="rejectModal = false"
         class="space-y-6">

        {{-- Page Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end justify-between border-b border-line pb-5">
            <div>
                <x-eyebrow>Financeiro · Modelo A (1 crédito = R$ 1,00)</x-eyebrow>
                <h1 class="mt-1 font-serif text-2xl sm:text-3xl text-ink">
                    Aprovação de Pagamentos &amp; Liberação de Créditos
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600 max-w-2xl">
                    Confira comprovantes e TXIDs antes de liberar saldo. Todo valor aprovado é creditado integralmente (1:1, sem bônus ou deságio) no razão auditável da empresa.
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-4 py-2 text-xs font-semibold text-ink transition shrink-0">
                <x-icon name="arrow-left" class="size-3.5" />
                <span>Voltar ao Painel</span>
            </a>
        </div>

        {{-- Summary KPIs --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-accent-200 bg-surface p-5 shadow-sm flex items-center justify-between gap-4">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-accent-700">Volume Pendente de Conferência</span>
                    <p class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums">{{ $currency($pendingTotal) }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 tabular-nums">{{ $counts['pendente'] ?? 0 }} solicitação(ões) aguardando revisão</p>
                </div>
                <span class="h-11 w-11 rounded-lg bg-accent-50 border border-accent-200 text-accent-700 flex items-center justify-center shrink-0">
                    <x-icon name="qr-code" class="size-5" />
                </span>
            </div>

            <div class="rounded-xl border border-money-200 bg-surface p-5 shadow-sm flex items-center justify-between gap-4">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-money-700">Total Aprovado (Histórico)</span>
                    <p class="mt-1 font-mono text-2xl font-bold text-money-700 tabular-nums">{{ $currency($approvedTotal) }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 tabular-nums">{{ $counts['aprovado'] ?? 0 }} pagamento(s) convertidos em créditos</p>
                </div>
                <span class="h-11 w-11 rounded-lg bg-money-50 border border-money-200 text-money-700 flex items-center justify-center shrink-0">
                    <x-icon name="circle-check" class="size-5" />
                </span>
            </div>
        </div>

        {{-- Filter Tabs & Search --}}
        <div class="bg-surface rounded-xl border border-line p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-1.5" role="navigation" aria-label="Filtrar pagamentos por status">
                @foreach (['pendente' => 'Pendentes', 'aprovado' => 'Aprovados', 'recusado' => 'Rejeitados', 'expirado' => 'Expirados', 'todos' => 'Todos'] as $tabKey => $tabLabel)
                    <a href="{{ route('admin.payments.index', array_filter(['tab' => $tabKey, 'q' => $search ?: null])) }}"
                       @if($currentTab === $tabKey) aria-current="page" @endif
                       class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-semibold transition {{ $currentTab === $tabKey ? 'bg-ink text-paper' : 'border border-line bg-paper text-slate-700 hover:border-ink hover:text-ink' }}">
                        <span>{{ $tabLabel }}</span>
                        <span class="font-mono text-[11px] tabular-nums {{ $currentTab === $tabKey ? 'text-slate-300' : 'text-slate-500' }}">({{ $counts[$tabKey] ?? 0 }})</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.payments.index') }}" class="flex items-center gap-2 w-full lg:w-auto">
                <input type="hidden" name="tab" value="{{ $currentTab }}">
                <label for="payments-search" class="sr-only">Buscar por empresa, CNPJ, solicitante ou TXID</label>
                <div class="relative flex-1 lg:w-72">
                    <x-icon name="search" class="size-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input id="payments-search" type="search" name="q" value="{{ $search }}"
                           placeholder="Empresa, CNPJ ou TXID..."
                           class="w-full rounded-lg border-line bg-paper pl-8 pr-3 py-2 text-xs text-ink focus:border-ink focus:ring-ink">
                </div>
                <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-paper transition shrink-0">
                    Buscar
                </button>
                @if($search !== '')
                    <a href="{{ route('admin.payments.index', ['tab' => $currentTab]) }}"
                       class="rounded-lg border border-line bg-surface hover:border-ink px-3 py-2 text-xs font-medium text-slate-700 shrink-0">
                        Limpar
                    </a>
                @endif
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
            <div class="table-scroll-region">
                <table class="min-w-full divide-y divide-line text-xs">
                    <thead class="bg-paper text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Empresa / Solicitante</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Plano / Contrato</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Método &amp; TXID</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Valor / Créditos</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status &amp; Conferência</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($payments as $payment)
                            @php
                                $companyName = $payment->company?->trade_name ?? $payment->company?->legal_name ?? 'Empresa';
                                $statusBadge = match ($payment->status) {
                                    \App\Models\Payment::STATUS_APPROVED => ['Aprovado', 'bg-money-50 border-money-200 text-money-700'],
                                    \App\Models\Payment::STATUS_REJECTED => ['Rejeitado', 'bg-danger-50 border-danger-200 text-danger-700'],
                                    \App\Models\Payment::STATUS_EXPIRED => ['Expirado', 'bg-paper border-line text-slate-600'],
                                    default => ['Pendente', 'bg-accent-50 border-accent-200 text-accent-700'],
                                };
                            @endphp
                            <tr class="hover:bg-paper/45 transition-colors">
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-ink">{{ $companyName }}</p>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        <span class="font-mono tabular-nums">{{ $payment->company?->cnpj }}</span>
                                        @if($payment->requestedBy)
                                            · {{ $payment->requestedBy->name }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-4 py-3.5 text-slate-700">
                                    {{ $payment->contract?->plan?->name ?? 'Recarga Avulsa' }}
                                </td>
                                <td class="px-4 py-3.5 text-slate-700">
                                    <span class="uppercase font-medium">{{ $payment->payment_method }}</span>
                                    @if ($payment->txid)
                                        <span class="block font-mono text-[10px] text-slate-500 mt-0.5">{{ $payment->txid }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-ink tabular-nums whitespace-nowrap">
                                    {{ $currency($payment->amount) }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-[11px] font-semibold {{ $statusBadge[1] }}">
                                        {{ $statusBadge[0] }}
                                    </span>
                                    @if ($payment->reviewer)
                                        <p class="mt-1 text-[11px] text-slate-500">Por: {{ $payment->reviewer->name }}</p>
                                    @endif
                                    @if ($payment->admin_notes)
                                        <p class="mt-0.5 text-[11px] text-slate-600">{{ $payment->admin_notes }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @if ($payment->receipt_path)
                                            <a href="{{ route('payments.receipt', $payment) }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1 rounded-lg border border-line bg-paper hover:border-ink px-2.5 py-1.5 font-medium text-ink transition">
                                                <x-icon name="file-text" class="size-3.5" />
                                                <span>Comprovante</span>
                                            </a>
                                        @endif
                                        @if ($payment->status === \App\Models\Payment::STATUS_PENDING)
                                            @if ($canManageFinance)
                                                <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 rounded-lg bg-money-600 hover:bg-money-700 px-3 py-1.5 font-semibold text-white transition">
                                                        <x-icon name="check" class="size-3.5" />
                                                        <span>Aprovar</span>
                                                    </button>
                                                </form>
                                                <button type="button"
                                                        @click="openReject('{{ route('admin.payments.reject', $payment) }}', '{{ addslashes($companyName) }}')"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-danger-200 bg-danger-50 hover:bg-danger-100 px-2.5 py-1.5 font-semibold text-danger-700 transition">
                                                    <x-icon name="x" class="size-3.5" />
                                                    <span>Rejeitar</span>
                                                </button>
                                            @else
                                                <span class="text-[11px] text-slate-500 inline-flex items-center gap-1">
                                                    <x-icon name="lock" class="size-3" />
                                                    <span>Somente leitura</span>
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                    Nenhum pagamento encontrado para este filtro.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $payments->links() }}</div>

        {{-- Reject Payment Modal --}}
        <div x-show="rejectModal" x-cloak
             role="dialog" aria-modal="true" aria-labelledby="reject-payment-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="rejectModal = false" class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="reject-payment-title" class="font-serif text-xl text-ink">Rejeitar Pagamento</h2>
                        <p class="mt-0.5 text-xs text-slate-600">Empresa: <strong class="text-ink" x-text="rejectCompany"></strong></p>
                    </div>
                    <button type="button" data-compact-btn @click="rejectModal = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>

                <form method="POST" :action="rejectAction" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="reject_admin_notes" class="block text-xs font-semibold text-ink mb-1">Motivo da recusa (visível na auditoria)</label>
                        <textarea id="reject_admin_notes" name="admin_notes" rows="3" required
                                  placeholder="Ex.: Comprovante ilegível ou valor divergente da fatura."
                                  class="w-full rounded-lg border-line bg-paper px-3 py-2 text-xs text-ink focus:border-ink focus:ring-ink"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-line">
                        <button type="button" @click="rejectModal = false"
                                class="rounded-lg border border-line bg-paper hover:border-ink px-4 py-2 text-xs font-semibold text-ink">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-lg bg-danger-600 hover:bg-danger-700 px-4 py-2 text-xs font-semibold text-white">
                            Confirmar Recusa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
