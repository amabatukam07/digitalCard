@php
    $currency = static fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $canManageOrders = (bool) auth()->user()?->canManageOrders();
    $operableStatuses = [
        \App\Models\Order::STATUS_REQUESTED,
        \App\Models\Order::STATUS_SEPARATING,
        \App\Models\Order::STATUS_SHIPPED,
    ];
@endphp

<x-admin-layout title="Pedidos & Entregas">
    <div x-data="{
            logisticsModal: false,
            logisticsAction: '',
            logisticsOrderNum: '',
            logisticsStatus: 'separating',
            logisticsCarrier: 'Frota NexoSupri',
            logisticsTracking: '',
            cancelModal: false,
            cancelAction: '',
            cancelOrderNum: '',
            cancelCompany: '',
            openLogistics(action, orderNum, status, tracking) {
                this.logisticsAction = action;
                this.logisticsOrderNum = orderNum;
                this.logisticsStatus = status === 'requested' ? 'separating' : (status === 'separating' ? 'shipped' : 'delivered');
                this.logisticsTracking = tracking || '';
                this.logisticsModal = true;
            },
            openCancel(action, orderNum, companyName) {
                this.cancelAction = action;
                this.cancelOrderNum = orderNum;
                this.cancelCompany = companyName;
                this.cancelModal = true;
            }
         }"
         @keydown.escape.window="logisticsModal = false; cancelModal = false"
         class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end justify-between border-b border-line pb-5">
            <div>
                <x-eyebrow>Operação B2B · Separação, Expedição &amp; Entrega</x-eyebrow>
                <h1 class="mt-1 font-serif text-2xl sm:text-3xl text-ink">Pedidos das Empresas</h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600 max-w-2xl">
                    Acompanhe o fluxo completo dos pedidos pagos com créditos NexoSupri, atualize etapas logísticas com rastreio ou registre entregas confirmadas.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('admin.payments.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-3.5 py-2 text-xs font-semibold text-ink transition">
                    <x-icon name="qr-code" class="size-3.5 text-accent-600" />
                    <span>Pagamentos &amp; Créditos</span>
                </a>
                <a href="{{ route('admin.dashboard') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-paper transition">
                    <span>Voltar ao Painel</span>
                </a>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-accent-200 bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-accent-700">Em Operação</p>
                <p class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums">{{ $orderStats['pendingCount'] }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Solicitados, em separação ou enviados</p>
            </div>
            <div class="rounded-xl border border-money-200 bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-money-700">Entregues</p>
                <p class="mt-1 font-mono text-2xl font-bold text-money-700 tabular-nums">{{ $orderStats['deliveredCount'] }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Comprovantes logísticos registrados</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Volume Líquido (Pedidos Ativos)</p>
                <p class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums">{{ $currency($orderStats['netSales']) }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Exclui cancelados, devolvidos e pendentes de gestor</p>
            </div>
        </div>

        {{-- Status Tabs & Search --}}
        <div class="bg-surface rounded-xl border border-line p-4 space-y-3">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1" role="navigation" aria-label="Filtrar pedidos por status">
                    <a href="{{ route('admin.orders.index', array_filter(['q' => $search ?: null])) }}"
                       @if($currentStatus === '') aria-current="page" @endif
                       class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shrink-0 transition {{ $currentStatus === '' ? 'bg-ink text-paper' : 'border border-line bg-paper text-slate-700 hover:border-ink' }}">
                        <span>Todos</span>
                        <span class="font-mono text-[10px] tabular-nums opacity-80">({{ $totalOrdersCount }})</span>
                    </a>
                    @foreach (\App\Enums\OrderStatus::cases() as $statusCase)
                        <a href="{{ route('admin.orders.index', array_filter(['status' => $statusCase->value, 'q' => $search ?: null])) }}"
                           @if($currentStatus === $statusCase->value) aria-current="page" @endif
                           class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shrink-0 transition {{ $currentStatus === $statusCase->value ? 'bg-ink text-paper' : 'border border-line bg-paper text-slate-700 hover:border-ink' }}">
                            <span>{{ $statusCase->label() }}</span>
                            <span class="font-mono text-[10px] tabular-nums opacity-80">({{ $statusCountsRaw[$statusCase->value] ?? 0 }})</span>
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('admin.orders.index') }}" class="flex items-center gap-2 shrink-0">
                    @if($currentStatus !== '')
                        <input type="hidden" name="status" value="{{ $currentStatus }}">
                    @endif
                    <label for="orders-search" class="sr-only">Buscar pedido por número, empresa, CNPJ ou código de rastreio</label>
                    <div class="relative w-full sm:w-64">
                        <x-icon name="search" class="size-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input id="orders-search" type="search" name="q" value="{{ $search }}"
                               placeholder="#PED, empresa, CNPJ ou rastreio..."
                               class="w-full rounded-lg border-line bg-paper pl-8 pr-3 py-1.5 text-xs text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-paper transition">
                        Buscar
                    </button>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
            <div class="table-scroll-region">
                <table class="min-w-full divide-y divide-line text-xs">
                    <thead class="bg-paper text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Pedido</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Empresa / Solicitante</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status &amp; Rastreio</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Créditos / Total</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Data</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($orders as $order)
                            @php
                                $orderNum = '#PED-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                                $orderCompanyName = $order->company?->trade_name ?? $order->company?->legal_name ?? 'Empresa não vinculada';
                                $statusEnum = $order->statusEnum();
                                $badgeClass = match ($statusEnum) {
                                    \App\Enums\OrderStatus::Delivered => 'bg-money-50 border-money-200 text-money-700',
                                    \App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::Returned => 'bg-danger-50 border-danger-200 text-danger-700',
                                    \App\Enums\OrderStatus::AwaitingApproval => 'bg-paper border-line text-slate-600',
                                    default => 'bg-accent-50 border-accent-200 text-accent-700',
                                };
                            @endphp
                            <tr class="hover:bg-paper/45 transition-colors">
                                <td class="px-4 py-3.5">
                                    <span class="font-mono font-bold text-ink">{{ $orderNum }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ $order->items->count() }} {{ $order->items->count() === 1 ? 'item' : 'itens' }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-ink">{{ $orderCompanyName }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        <span class="font-mono tabular-nums">{{ $order->company?->cnpj ?? '' }}</span> · {{ $order->user?->name }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex rounded-md border px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">
                                        {{ $statusEnum->label() }}
                                    </span>
                                    @if ($order->tracking_code)
                                        <span class="block font-mono text-[10px] text-slate-500 mt-1">Rastreio: {{ $order->tracking_code }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-ink tabular-nums whitespace-nowrap">
                                    {{ $currency($order->total_amount) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-500 tabular-nums whitespace-nowrap">
                                    {{ $order->created_at?->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="inline-flex items-center gap-1 rounded-lg border border-line bg-paper hover:border-ink px-2.5 py-1.5 font-semibold text-ink transition">
                                            <x-icon name="eye" class="size-3.5" />
                                            <span>Ver</span>
                                        </a>
                                        @if ($canManageOrders && in_array($order->status, $operableStatuses, true))
                                            <button type="button"
                                                    @click="openLogistics('{{ route('admin.orders.status', $order) }}', '{{ $orderNum }}', '{{ $order->status }}', '{{ addslashes((string) $order->tracking_code) }}')"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-line bg-surface hover:border-ink px-2.5 py-1.5 font-semibold text-ink transition">
                                                <x-icon name="truck" class="size-3.5" />
                                                <span>Logística</span>
                                            </button>
                                            <form method="POST" action="{{ route('admin.orders.deliver', $order) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 rounded-lg bg-money-600 hover:bg-money-700 px-2.5 py-1.5 font-semibold text-white transition">
                                                    <x-icon name="check" class="size-3.5" />
                                                    <span>Entregue</span>
                                                </button>
                                            </form>
                                            <button type="button"
                                                    @click="openCancel('{{ route('admin.orders.cancel', $order) }}', '{{ $orderNum }}', '{{ addslashes($orderCompanyName) }}')"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-danger-200 bg-danger-50 hover:bg-danger-100 px-2.5 py-1.5 font-semibold text-danger-700 transition">
                                                <span>Cancelar</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                    Nenhum pedido encontrado para os filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $orders->links() }}</div>

        {{-- Logistics Status Modal --}}
        <div x-show="logisticsModal" x-cloak
             role="dialog" aria-modal="true" aria-labelledby="logistics-modal-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="logisticsModal = false" class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="logistics-modal-title" class="font-serif text-xl text-ink">Atualizar Logística</h2>
                        <p class="mt-0.5 text-xs text-slate-600">Pedido <strong class="font-mono text-ink" x-text="logisticsOrderNum"></strong></p>
                    </div>
                    <button type="button" data-compact-btn @click="logisticsModal = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>

                <form method="POST" :action="logisticsAction" class="space-y-3 text-xs">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="log_status" class="block font-semibold text-ink mb-1">Próxima etapa</label>
                        <select id="log_status" name="status" x-model="logisticsStatus" required
                                class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            <option value="separating">Em separação</option>
                            <option value="shipped">Produto enviado</option>
                            <option value="delivered">Produto entregue</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="log_carrier" class="block font-semibold text-ink mb-1">Transportadora / Frota</label>
                            <input id="log_carrier" type="text" name="carrier" x-model="logisticsCarrier"
                                   class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                        </div>
                        <div>
                            <label for="log_tracking" class="block font-semibold text-ink mb-1">Código de rastreio</label>
                            <input id="log_tracking" type="text" name="tracking_code" x-model="logisticsTracking" placeholder="Opcional"
                                   class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                    </div>
                    <div>
                        <label for="log_receiver" class="block font-semibold text-ink mb-1">Recebedor (se entregue)</label>
                        <input id="log_receiver" type="text" name="receiver_name" placeholder="Nome de quem recebeu"
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="log_notes" class="block font-semibold text-ink mb-1">Observações operacionais</label>
                        <textarea id="log_notes" name="notes" rows="2" placeholder="Opcional"
                                  class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-line">
                        <button type="button" @click="logisticsModal = false" class="rounded-lg border border-line bg-paper px-4 py-2 font-semibold text-ink">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper">
                            Salvar Etapa
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Cancel Order Confirmation Modal (Nielsen #5: Error Prevention) --}}
        <div x-show="cancelModal" x-cloak
             role="dialog" aria-modal="true" aria-labelledby="cancel-order-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="cancelModal = false" class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-start gap-3">
                    <span class="h-10 w-10 rounded-lg bg-danger-50 border border-danger-200 text-danger-700 flex items-center justify-center shrink-0">
                        <x-icon name="alert-triangle" class="size-5" />
                    </span>
                    <div>
                        <h2 id="cancel-order-title" class="font-serif text-xl text-ink">Cancelar Pedido &amp; Estornar</h2>
                        <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                            Você está prestes a cancelar <strong class="font-mono text-ink" x-text="cancelOrderNum"></strong> (<strong x-text="cancelCompany"></strong>). Os créditos debitados serão estornados na carteira da empresa e o estoque será recomposto com registro em auditoria.
                        </p>
                    </div>
                </div>
                <form method="POST" :action="cancelAction" class="flex justify-end gap-2 pt-3 border-t border-line">
                    @csrf
                    @method('PATCH')
                    <button type="button" @click="cancelModal = false"
                            class="rounded-lg border border-line bg-paper px-4 py-2 text-xs font-semibold text-ink">
                        Voltar
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-danger-600 hover:bg-danger-700 px-4 py-2 text-xs font-semibold text-white">
                        Confirmar Cancelamento
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
