<x-app-layout :metaTitle="'Pedido #PED-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)" :metaDescription="'Detalhes e acompanhamento do pedido corporativo #PED-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) . ' na NexoSupri.'">
    <div class="w-full max-w-[1440px] mx-auto py-8 px-4 sm:px-6 lg:px-8 xl:px-10 space-y-8"
         x-data="{ copiedTracking: false }">
        <div class="flex items-center justify-between">
            <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 hover:text-ink transition">
                <x-icon name="arrow-left" class="size-4" />
                <span>Voltar para Pedidos</span>
            </a>

            <div class="flex flex-wrap items-center gap-2">
                @if($canApprove ?? false)
                    <form action="{{ route('orders.approve', $order) }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 bg-money-600 hover:bg-money-700 text-paper text-xs font-semibold px-4 py-2 rounded-lg transition">
                            <x-icon name="check" class="size-3.5" />
                            <span>Aprovar e debitar créditos</span>
                        </button>
                    </form>
                @endif

                <form action="{{ route('orders.reorder', $order) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 bg-ink hover:bg-slate-800 text-paper text-xs font-semibold px-3.5 py-2 rounded-lg transition">
                        <x-icon name="repeat" class="size-3.5" />
                        <span>Repetir pedido</span>
                    </button>
                </form>

                <button onclick="window.print()" type="button"
                        class="inline-flex items-center gap-1.5 bg-surface hover:bg-paper-dim border border-line text-slate-700 text-xs font-medium px-3.5 py-2 rounded-lg transition">
                    <x-icon name="printer" class="size-4 text-slate-500" />
                    <span>Imprimir</span>
                </button>
            </div>
        </div>

        <div class="bg-surface rounded-xl border border-line overflow-hidden p-6 sm:p-8 space-y-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-line gap-4">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="font-mono tabular-nums text-xl font-semibold text-ink">
                            #PED-{{ str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-medium border {{ $order->status === \App\Models\Order::STATUS_DELIVERED ? 'bg-money-50 text-money-900 border-money-200' : (in_array($order->status, [\App\Models\Order::STATUS_CANCELLED, \App\Models\Order::STATUS_RETURNED], true) ? 'bg-danger-50 text-danger-900 border-danger-200' : 'bg-accent-50 text-accent-900 border-accent-200') }}">
                            {{ $order->status }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ $order->company?->trade_name }} ({{ $order->company?->cnpj }}) · Criado em {{ $order->created_at->format('d/m/Y \à\s H:i') }}
                    </p>
                </div>

                <div class="text-left sm:text-right">
                    <span class="text-xs text-slate-500 block">
                        {{ (float) $order->credits_debited > 0 ? 'Total debitado da carteira' : 'Total previsto em créditos' }}
                    </span>
                    <span class="font-mono tabular-nums text-2xl font-semibold text-ink">
                        {{ number_format((float) $order->total_amount, 2, ',', '.') }} créditos
                    </span>
                    <span class="block text-xs text-slate-500 font-mono">R$ {{ number_format((float) $order->total_amount, 2, ',', '.') }}</span>
                </div>
            </div>

            <div class="space-y-5">
                <h2 class="text-xs font-medium text-slate-500">Linha do tempo e histórico de status</h2>

                @php
                    $steps = [
                        \App\Models\Order::STATUS_AWAITING_APPROVAL,
                        \App\Models\Order::STATUS_REQUESTED,
                        \App\Models\Order::STATUS_SEPARATING,
                        \App\Models\Order::STATUS_SHIPPED,
                        \App\Models\Order::STATUS_DELIVERED,
                    ];
                    $isCancelled = in_array($order->status, [\App\Models\Order::STATUS_CANCELLED, \App\Models\Order::STATUS_RETURNED], true);
                    $currentIndex = array_search($order->status, $steps, true);
                    $historyByStatus = $order->statusHistories ? $order->statusHistories->keyBy('to_status') : collect();
                @endphp

                @if($isCancelled)
                    <div class="rounded-lg border border-danger-200 bg-danger-50 p-4 flex items-center gap-3 text-xs text-danger-900">
                        <x-icon name="circle-alert" class="size-5 text-danger-600 shrink-0" />
                        <div>
                            <strong class="font-semibold block">Pedido em estado: {{ $order->status }}</strong>
                            <span class="text-danger-700">Os créditos debitados foram estornados na carteira da empresa e o estoque foi recomposto.</span>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 text-xs">
                    @foreach($steps as $idx => $stepLabel)
                        @php
                            $done = ! $isCancelled && ($order->status === \App\Models\Order::STATUS_DELIVERED || ($currentIndex !== false && $idx <= $currentIndex));
                            $stepHistory = $historyByStatus->get($stepLabel);
                            $stepDate = $stepHistory?->created_at;
                        @endphp
                        <div class="flex sm:flex-col items-start gap-2 rounded-lg border p-3 {{ $done ? 'border-money-200 bg-money-50/60 text-money-950' : 'border-line bg-paper text-slate-500' }}">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex size-5 items-center justify-center rounded-full text-[11px] font-mono font-semibold {{ $done ? 'bg-money-600 text-paper' : 'bg-paper-dim text-slate-600' }}">
                                    @if($done)
                                        <x-icon name="check" class="size-3" />
                                    @else
                                        {{ $idx + 1 }}
                                    @endif
                                </span>
                                <span class="font-semibold text-ink">{{ $stepLabel }}</span>
                            </div>
                            @if($stepDate)
                                <span class="text-[11px] text-slate-500 font-mono tabular-nums">{{ $stepDate->format('d/m H:i') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($order->statusHistories && $order->statusHistories->isNotEmpty())
                    <ul class="space-y-2 border-l border-line pl-4 text-xs">
                        @foreach($order->statusHistories as $hist)
                            <li class="text-slate-700">
                                <strong class="text-ink font-medium">{{ $hist->to_status }}</strong>
                                <span class="text-slate-500 font-mono tabular-nums">({{ $hist->created_at?->format('d/m/Y H:i') }})</span>
                                @if($hist->notes)
                                    — <span>{{ $hist->notes }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-paper p-4 rounded-lg border border-line text-xs">
                <div>
                    <span class="text-xs text-slate-500 block mb-1">Solicitante &amp; Aprovação</span>
                    <p class="font-semibold text-ink">{{ $order->user->name ?? 'Colaborador' }}</p>
                    <p class="text-slate-600">{{ $order->department ?: 'Geral' }}</p>
                    @if($order->approver)
                        <p class="text-[11px] text-money-700 mt-1">Aprovado por {{ $order->approver->name }}</p>
                    @endif
                </div>
                <div>
                    <span class="text-xs text-slate-500 block mb-1">Endereço de entrega</span>
                    <p class="text-ink font-medium leading-relaxed">{{ $order->shipping_address }}</p>
                    @if($order->shipping_cep)
                        <p class="text-slate-500 mt-0.5 font-mono">CEP: {{ $order->shipping_cep }}</p>
                    @endif
                </div>
                <div>
                    <span class="text-xs text-slate-500 block mb-1">Logística &amp; Entrega</span>
                    <p class="font-mono font-semibold text-ink">{{ $order->tracking_code ?: 'Aguardando expedição' }}</p>
                    @if($order->delivery)
                        <p class="text-slate-600 mt-0.5">{{ $order->delivery->carrier }} · {{ $order->delivery->receiver_name }}</p>
                    @endif
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-xs font-medium text-slate-500">Insumos do pedido</h3>
                <div class="divide-y divide-line">
                    @foreach($order->items as $item)
                        <div class="py-3.5 flex items-center justify-between gap-4 text-xs">
                            <div>
                                <p class="font-semibold text-ink">{{ $item->display_name }}</p>
                                <p class="text-slate-600 mt-0.5">
                                    SKU {{ $item->product_sku }} · {{ $item->unit_presentation }} · Qtd: {{ $item->quantity }} × R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}
                                </p>
                            </div>
                            <span class="font-mono tabular-nums font-semibold text-sm text-ink whitespace-nowrap">
                                R$ {{ number_format($item->quantity * (float) $item->unit_price, 2, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-4 border-t border-line space-y-2 text-xs">
                <div class="flex justify-between text-slate-600">
                    <span>Subtotal dos insumos</span>
                    <span class="font-mono tabular-nums">R$ {{ number_format((float) $order->subtotal_amount, 2, ',', '.') }}</span>
                </div>
                @if((float) $order->discount_amount > 0)
                    <div class="flex justify-between text-money-700 font-medium">
                        <span>Desconto ({{ $order->coupon_code }})</span>
                        <span class="font-mono tabular-nums">- R$ {{ number_format((float) $order->discount_amount, 2, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-slate-600">
                    <span>Frete</span>
                    <span class="font-mono tabular-nums">{{ (float) $order->shipping_cost > 0 ? 'R$ '.number_format((float) $order->shipping_cost, 2, ',', '.') : 'Grátis' }}</span>
                </div>
                <div class="flex justify-between items-baseline pt-3 border-t border-line text-sm font-semibold text-ink">
                    <span>Total em Créditos (Modelo A)</span>
                    <span class="font-mono tabular-nums text-xl text-money-700">
                        {{ number_format((float) $order->total_amount, 2, ',', '.') }} créditos
                    </span>
                </div>
            </div>

            @if (in_array($order->status, [\App\Models\Order::STATUS_AWAITING_APPROVAL, \App\Models\Order::STATUS_REQUESTED], true) && (auth()->id() === $order->user_id || auth()->user()->isCompanyManager($order->company)))
                <div class="flex flex-col items-start justify-between gap-3 rounded-lg border border-danger-200 bg-danger-50 p-4 sm:flex-row sm:items-center">
                    <div>
                        <span class="block text-xs font-semibold text-danger-900">Cancelar este pedido?</span>
                        <span class="text-xs text-danger-700">Eventuais créditos debitados serão estornados na carteira da empresa.</span>
                    </div>

                    <form action="{{ route('orders.cancel', $order) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-lg border border-danger-200 bg-surface px-4 py-2 text-xs font-semibold text-danger-700 hover:bg-danger-100">
                            Cancelar Pedido
                        </button>
                    </form>
                </div>
            @elseif ($order->canRequestRegretReturn() && auth()->user()->belongsToCompany((int) $order->company_id))
                <div class="flex flex-col items-start justify-between gap-3 rounded-lg border border-accent-200 bg-accent-50 p-4 sm:flex-row sm:items-center">
                    <div>
                        <span class="block text-xs font-semibold text-accent-950">Solicitar devolução (até 7 dias após entrega)</span>
                        <span class="text-xs text-accent-800">Os créditos correspondentes serão devolvidos à carteira da empresa com registro auditável.</span>
                    </div>

                    <form action="{{ route('orders.return', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-lg bg-accent-600 hover:bg-accent-700 px-4 py-2 text-xs font-semibold text-paper">
                            Registrar Devolução
                        </button>
                    </form>
                </div>
            @endif

            @can('admin')
                @if ($order->statusEnum()->isOpen() && $order->status !== \App\Models\Order::STATUS_AWAITING_APPROVAL)
                    <div class="mt-8 space-y-4 rounded-xl border border-line bg-paper p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <x-eyebrow>Operação / Logística NexoSupri</x-eyebrow>
                                <h4 class="mt-1 text-sm font-semibold text-ink">Atualizar etapa de entrega</h4>
                            </div>

                            <form action="{{ route('admin.orders.cancel', $order) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-lg border border-danger-200 bg-danger-50 px-3 py-1.5 text-xs font-semibold text-danger-700 hover:bg-danger-100">
                                    Cancelar Pedido (Backoffice)
                                </button>
                            </form>
                        </div>

                        <form action="{{ route('admin.orders.status', $order) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Próximo status</label>
                                <select name="status" class="w-full rounded-lg border-line bg-surface px-3 py-2 text-xs text-ink">
                                    <option value="{{ \App\Models\Order::STATUS_SEPARATING }}">Em separação</option>
                                    <option value="{{ \App\Models\Order::STATUS_SHIPPED }}">Enviado</option>
                                    <option value="{{ \App\Models\Order::STATUS_DELIVERED }}">Produto entregue</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Código de rastreio</label>
                                <input type="text" name="tracking_code" value="{{ $order->tracking_code }}" placeholder="NXSP2026..."
                                       class="w-full rounded-lg border-line bg-surface px-3 py-2 text-xs text-ink">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Recebedor</label>
                                <input type="text" name="receiver_name" placeholder="Nome de quem recebeu"
                                       class="w-full rounded-lg border-line bg-surface px-3 py-2 text-xs text-ink">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full rounded-lg bg-money-600 hover:bg-money-700 px-4 py-2 text-xs font-semibold text-paper transition">
                                    Salvar status
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
