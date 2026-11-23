<x-admin-layout>
    <div class="w-full max-w-[1720px] mx-auto py-4 sm:py-6 space-y-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-line gap-4">
            <div>
                <x-eyebrow>Backoffice • Logística e despacho</x-eyebrow>
                <h1 class="font-serif text-3xl text-ink mt-1">Gestão de Pedidos</h1>
                <p class="text-sm text-slate-600 mt-0.5">Controle de envios e confirmação de entrega aos clientes compradores.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.recharges.index') }}"
                   class="bg-surface hover:bg-paper-dim border border-line text-ink text-xs font-medium px-4 py-2.5 rounded-lg transition inline-flex items-center gap-1.5">
                    <x-icon name="arrow-left" class="size-3.5" />
                    <span>Ver recargas pendentes</span>
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 bg-money-50 border border-money-200 text-money-900 rounded-xl flex items-center gap-3">
                <x-icon name="circle-check" class="size-5 text-money-700 shrink-0" />
                <p class="font-medium text-sm">{{ session('success') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 divide-y sm:grid-cols-3 sm:divide-y-0 sm:divide-x divide-line rounded-xl border border-line bg-surface">
            <div class="p-5">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-medium">Pedidos em aberto</span>
                    <x-icon name="clock" class="size-4 text-accent-600" />
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span class="font-mono tabular-nums text-2xl font-semibold text-ink">{{ $orderStats['pendingCount'] }}</span>
                    <span class="text-xs font-medium text-accent-800 bg-accent-50 px-2 py-0.5 rounded border border-accent-200">
                        Em andamento
                    </span>
                </div>
            </div>

            <div class="p-5">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-medium">Pedidos concluídos (Produto entregue)</span>
                    <x-icon name="circle-check" class="size-4 text-money-600" />
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span class="font-mono tabular-nums text-2xl font-semibold text-ink">{{ $orderStats['deliveredCount'] }}</span>
                    <span class="text-xs font-medium text-money-700 bg-money-50 px-2 py-0.5 rounded border border-money-200">
                        Entregues
                    </span>
                </div>
            </div>

            <div class="p-5">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-medium">Faturamento líquido em cartão</span>
                    <x-icon name="receipt" class="size-4" />
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span class="font-mono tabular-nums text-2xl font-semibold text-money-700 whitespace-nowrap">
                        R$ {{ number_format($orderStats['netSales'], 2, ',', '.') }}
                    </span>
                    <span class="text-xs text-slate-500">Exclui cancelados/devolvidos</span>
                </div>
            </div>
        </div>

        <div class="bg-surface rounded-xl border border-line overflow-hidden">
            <div class="p-6 border-b border-line flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h3 class="font-semibold text-ink text-base">Histórico completo de pedidos</h3>
                    <p class="text-xs text-slate-500">Avance etapas no detalhe do pedido ou confirme a entrega diretamente para liberar o cashback.</p>
                </div>

                <form action="{{ route('admin.orders.index') }}" method="GET" class="flex flex-wrap items-center gap-2 text-xs">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Pedido, cliente ou rastreio..."
                           class="rounded-lg border-line px-3 py-2 text-xs w-52">
                    <select name="status" class="rounded-lg border-line px-3 py-2 text-xs">
                        <option value="">Todos os status</option>
                        @foreach(\App\Enums\OrderStatus::cases() as $statusCase)
                            <option value="{{ $statusCase->value }}" {{ request('status') === $statusCase->value ? 'selected' : '' }}>
                                {{ $statusCase->label() }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-paper transition">
                        Filtrar
                    </button>
                    @if(request()->hasAny(['q', 'status']))
                        <a href="{{ route('admin.orders.index') }}" class="rounded-lg border border-line px-3 py-2 text-xs text-slate-600 hover:text-ink">
                            Limpar
                        </a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line text-xs font-medium text-slate-500 bg-paper">
                            <th class="py-3.5 px-6">Identificador</th>
                            <th class="py-3.5 px-6">Data</th>
                            <th class="py-3.5 px-6">Cliente</th>
                            <th class="py-3.5 px-6">Total do pedido</th>
                            <th class="py-3.5 px-6">Endereço de entrega</th>
                            <th class="py-3.5 px-6">Status</th>
                            <th class="py-3.5 px-6 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line text-xs">
                        @forelse($orders as $order)
                            <tr class="hover:bg-paper/50 transition">
                                <td class="py-4 px-6 font-mono tabular-nums font-semibold text-ink">
                                    <a href="{{ route('orders.show', $order) }}" class="hover:text-accent-600 underline">
                                        #PED-{{ str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                </td>
                                <td class="py-4 px-6 text-slate-600 whitespace-nowrap">
                                    {{ $order->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-medium text-ink">{{ $order->user->name ?? 'N/A' }}</p>
                                    <p class="text-xs text-slate-500">{{ $order->user->email ?? '' }}</p>
                                </td>
                                <td class="py-4 px-6 font-mono tabular-nums font-semibold text-sm text-ink whitespace-nowrap">
                                    R$ {{ number_format($order->total_amount, 2, ',', '.') }}
                                </td>
                                <td class="py-4 px-6 max-w-xs truncate text-slate-600" title="{{ $order->shipping_address }}">
                                    {{ $order->shipping_address }}
                                </td>
                                <td class="py-4 px-6">
                                    @if($order->status === \App\Models\Order::STATUS_DELIVERED)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-medium bg-money-50 text-money-900 border border-money-200 whitespace-nowrap">
                                            <span class="size-1.5 rounded-full bg-money-500"></span>
                                            {{ $order->status }}
                                        </span>
                                    @elseif(in_array($order->status, [\App\Models\Order::STATUS_CANCELLED, \App\Models\Order::STATUS_RETURNED], true))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-medium bg-danger-50 text-danger-800 border border-danger-200 whitespace-nowrap">
                                            <span class="size-1.5 rounded-full bg-danger-500"></span>
                                            {{ $order->status }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-medium bg-accent-50 text-accent-900 border border-accent-200 whitespace-nowrap">
                                            <span class="size-1.5 rounded-full bg-accent-500"></span>
                                            {{ $order->status }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="text-xs font-medium text-ink bg-paper hover:bg-paper-dim px-3 py-1.5 rounded-lg transition border border-line">
                                            Detalhes
                                        </a>

                                        @if($order->statusEnum()->isOpen())
                                            <form action="{{ route('admin.orders.deliver', $order) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="bg-money-600 hover:bg-money-700 text-paper font-semibold text-xs px-3.5 py-1.5 rounded-lg transition">
                                                    Entregar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    Nenhum pedido encontrado com os filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-6 border-t border-line">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</x-admin-layout>
