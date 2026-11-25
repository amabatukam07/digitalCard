<x-app-layout :metaTitle="'Pedidos da Empresa'" :metaDescription="'Histórico de pedidos corporativos, aprovações e entregas na NexoSupri.'">
    <div class="app-shell py-8 space-y-6" x-data="{ statusFilter: 'todos' }">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-line gap-4">
            <div>
                <x-eyebrow>{{ $company ? $company->trade_name : 'Compras B2B' }}</x-eyebrow>
                <h1 class="font-serif text-3xl text-ink mt-1">Pedidos Corporativos e Entregas</h1>
                <p class="text-xs sm:text-sm text-slate-600 mt-0.5">Acompanhe aprovações, separação, expedição e entregas da empresa.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-lg bg-surface border border-line p-1 text-xs font-medium">
                    <button @click="statusFilter = 'todos'" type="button"
                            :class="statusFilter === 'todos' ? 'bg-ink text-paper' : 'text-slate-600 hover:text-ink'"
                            class="px-3 py-1.5 rounded-md transition">
                        Todos ({{ $orders->total() }})
                    </button>
                    <button @click="statusFilter = 'aprovacao'" type="button"
                            :class="statusFilter === 'aprovacao' ? 'bg-accent-600 text-paper' : 'text-slate-600 hover:text-ink'"
                            class="px-3 py-1.5 rounded-md transition">
                        Aguardando aprovação
                    </button>
                    <button @click="statusFilter = 'entregue'" type="button"
                            :class="statusFilter === 'entregue' ? 'bg-money-600 text-paper' : 'text-slate-600 hover:text-ink'"
                            class="px-3 py-1.5 rounded-md transition">
                        Entregues
                    </button>
                </div>

                <a href="{{ route('catalog.index') }}"
                   class="inline-flex items-center gap-2 bg-ink hover:bg-slate-800 text-paper text-xs font-semibold px-4 py-2.5 rounded-lg transition">
                    <span>Novo pedido</span>
                    <x-icon name="arrow-right" class="size-4" />
                </a>
            </div>
        </div>

        <div class="space-y-4">
            @forelse($orders as $order)
                @php
                    $isAwaiting = $order->status === \App\Models\Order::STATUS_AWAITING_APPROVAL;
                    $isDelivered = $order->status === \App\Models\Order::STATUS_DELIVERED;
                @endphp
                <div x-show="statusFilter === 'todos' || (statusFilter === 'aprovacao' && {{ $isAwaiting ? 'true' : 'false' }}) || (statusFilter === 'entregue' && {{ $isDelivered ? 'true' : 'false' }})"
                     class="bg-surface rounded-xl border border-line hover:border-slate-300 transition overflow-hidden">

                    <div class="bg-paper px-6 py-3.5 border-b border-line flex flex-wrap items-center justify-between gap-4 text-xs">
                        <div class="flex flex-wrap items-center gap-6">
                            <div>
                                <span class="text-xs text-slate-500 block">Data do pedido</span>
                                <span class="font-medium text-ink">{{ $order->created_at->format('d/m/Y \à\s H:i') }}</span>
                            </div>
                            <div>
                                <span class="text-xs text-slate-500 block">Solicitante</span>
                                <span class="font-medium text-ink">{{ $order->user?->name ?? 'Colaborador' }}</span>
                            </div>
                            <div>
                                <span class="text-xs text-slate-500 block">Total (R$ / Créditos)</span>
                                <span class="font-mono tabular-nums font-semibold text-ink">
                                    R$ {{ number_format((float) $order->total_amount, 2, ',', '.') }}
                                    ({{ number_format((float) $order->total_amount, 2, ',', '.') }} cr.)
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono tabular-nums text-xs font-medium text-slate-500">
                                #PED-{{ str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) }}
                            </span>
                            @if($isAwaiting && ($isManager ?? false))
                                <form action="{{ route('orders.approve', $order) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="bg-money-600 hover:bg-money-700 text-paper text-xs font-semibold px-3.5 py-2 rounded-lg transition">
                                        Aprovar pedido
                                    </button>
                                </form>
                            @endif
                            <form action="{{ route('orders.reorder', $order) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink text-ink text-xs font-medium px-3 py-2 transition">
                                    <x-icon name="repeat" class="size-3.5 text-slate-600" />
                                    <span>Repetir</span>
                                </button>
                            </form>
                            <a href="{{ route('orders.show', $order) }}"
                               class="bg-ink hover:bg-slate-800 text-paper text-xs font-semibold px-3.5 py-2 rounded-lg transition">
                                Detalhes
                            </a>
                        </div>
                    </div>

                    <div class="p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                        <div class="space-y-3 flex-1">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-medium border {{ $isDelivered ? 'bg-money-50 text-money-900 border-money-200' : (in_array($order->status, [\App\Models\Order::STATUS_CANCELLED, \App\Models\Order::STATUS_RETURNED], true) ? 'bg-danger-50 text-danger-900 border-danger-200' : 'bg-accent-50 text-accent-900 border-accent-200') }}">
                                    {{ $order->status }}
                                </span>
                                @if($order->department)
                                    <span class="text-xs text-slate-500">Setor: {{ $order->department }}</span>
                                @endif
                            </div>

                            <div class="divide-y divide-line pt-1">
                                @foreach($order->items as $item)
                                    <div class="py-2 first:pt-0 last:pb-0 flex items-center justify-between gap-4 text-xs">
                                        <span class="font-medium text-ink truncate">{{ $item->quantity }}× {{ $item->display_name }}</span>
                                        <span class="font-mono tabular-nums font-semibold text-ink shrink-0">
                                            R$ {{ number_format($item->quantity * (float) $item->unit_price, 2, ',', '.') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="lg:w-72 p-4 rounded-lg bg-paper border border-line text-xs space-y-1.5 shrink-0">
                            <span class="text-xs font-medium text-slate-500 block">Endereço corporativo de entrega</span>
                            <p class="text-ink font-medium leading-relaxed">{{ $order->shipping_address }}</p>
                            @if($order->tracking_code)
                                <p class="pt-1 text-xs font-mono text-money-700 font-medium">Rastreio: {{ $order->tracking_code }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-surface rounded-xl border border-line p-12 text-center space-y-3">
                    <div class="mx-auto size-12 rounded-full bg-paper-dim text-slate-500 flex items-center justify-center">
                        <x-icon name="package" class="size-6" />
                    </div>
                    <h3 class="text-base font-semibold text-ink">Nenhum pedido registrado pela empresa</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        Utilize os créditos pré-pagos da empresa para solicitar insumos no catálogo NexoSupri.
                    </p>
                    <a href="{{ route('catalog.index') }}"
                       class="inline-block bg-ink hover:bg-slate-800 text-paper font-semibold text-xs px-5 py-2.5 rounded-lg transition">
                        Explorar catálogo
                    </a>
                </div>
            @endforelse
        </div>

        <div class="pt-2">
            {{ $orders->links() }}
        </div>
    </div>
</x-app-layout>
