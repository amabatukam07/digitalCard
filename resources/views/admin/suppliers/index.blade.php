@php
    $currency = static fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $canManagePurchasing = (bool) auth()->user()?->canManagePurchasing();
@endphp

<x-admin-layout title="Fornecedores & Estoque">
    <div x-data="{
            supplierModalOpen: false,
            poModalOpen: false,
            linkModalOpen: false,
            adjustModalOpen: false,
            poQty: 20,
            poCost: 0,
            poFreight: 0,
            adjustProductId: '{{ $products->first()?->id ?? '' }}',
            get poTotalPreview() {
                return (Number(this.poQty || 0) * Number(this.poCost || 0)) + Number(this.poFreight || 0);
            }
         }"
         @keydown.escape.window="supplierModalOpen = false; poModalOpen = false; linkModalOpen = false; adjustModalOpen = false"
         class="space-y-6">

        {{-- Header & Demand Actions --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end justify-between border-b border-line pb-5">
            <div>
                <x-eyebrow>Compras, Suprimentos &amp; Inventário</x-eyebrow>
                <h1 class="mt-1 font-serif text-2xl sm:text-3xl text-ink">Fornecedores, Ordens de Compra &amp; Estoque</h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600 max-w-2xl">
                    Homologue fornecedores, vincule cotações por SKU, emita e receba ordens de compra (com atualização automática de custo e estoque) e registre inventário.
                </p>
            </div>

            @if ($canManagePurchasing)
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <button type="button" @click="poModalOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-paper transition">
                        <x-icon name="plus" class="size-3.5" />
                        <span>Nova Ordem de Compra</span>
                    </button>
                    <button type="button" @click="supplierModalOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-3.5 py-2 text-xs font-semibold text-ink transition">
                        <x-icon name="package" class="size-3.5" />
                        <span>Novo Fornecedor</span>
                    </button>
                    <button type="button" @click="linkModalOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-3.5 py-2 text-xs font-semibold text-ink transition">
                        <x-icon name="tag" class="size-3.5" />
                        <span>Vincular Cotação</span>
                    </button>
                    <button type="button" @click="adjustModalOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-3.5 py-2 text-xs font-semibold text-ink transition">
                        <x-icon name="sliders-horizontal" class="size-3.5" />
                        <span>Ajuste de Inventário</span>
                    </button>
                </div>
            @endif
        </div>

        {{-- KPIs --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Fornecedores Homologados</p>
                <p class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums">{{ $supplierStats['suppliersCount'] }}</p>
            </div>
            <div class="rounded-xl border border-accent-200 bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-accent-700">OCs em Aberto</p>
                <p class="mt-1 font-mono text-2xl font-bold text-accent-700 tabular-nums">{{ $supplierStats['openPoCount'] }}</p>
            </div>
            <div class="rounded-xl border border-money-200 bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-money-700">OCs Recebidas</p>
                <p class="mt-1 font-mono text-2xl font-bold text-money-700 tabular-nums">{{ $supplierStats['receivedPoCount'] }}</p>
            </div>
            <div class="rounded-xl border {{ $supplierStats['lowStockCount'] > 0 ? 'border-danger-200' : 'border-line' }} bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide {{ $supplierStats['lowStockCount'] > 0 ? 'text-danger-700' : 'text-slate-500' }}">Itens em Estoque Crítico</p>
                <p class="mt-1 font-mono text-2xl font-bold {{ $supplierStats['lowStockCount'] > 0 ? 'text-danger-700' : 'text-ink' }} tabular-nums">{{ $supplierStats['lowStockCount'] }}</p>
            </div>
        </div>

        {{-- Purchase Orders List --}}
        <div class="overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
            <div class="px-5 py-4 border-b border-line flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-serif text-xl text-ink">Ordens de Compra</h2>
                    <p class="text-xs text-slate-500">Ao confirmar a NF-e de entrada, o custo do produto e o saldo de estoque são atualizados automaticamente.</p>
                </div>
            </div>
            <div class="table-scroll-region">
                <table class="min-w-full divide-y divide-line text-xs">
                    <thead class="bg-paper text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">OC</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Fornecedor</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Itens &amp; Custo Unit.</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Total (c/ frete)</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Recebimento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($purchaseOrders as $po)
                            @php
                                $poNumber = '#OC-' . str_pad((string) $po->id, 5, '0', STR_PAD_LEFT);
                            @endphp
                            <tr class="hover:bg-paper/45 transition-colors">
                                <td class="px-4 py-3.5 font-mono font-bold text-ink">{{ $poNumber }}</td>
                                <td class="px-4 py-3.5 font-medium text-ink">{{ $po->supplier?->trade_name ?: $po->supplier?->legal_name }}</td>
                                <td class="px-4 py-3.5 text-slate-700 space-y-0.5">
                                    @foreach ($po->items as $poItem)
                                        <div>{{ $poItem->quantity }}× {{ $poItem->product?->name }} <span class="font-mono text-slate-500">({{ $currency($poItem->unit_cost) }})</span></div>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-ink tabular-nums whitespace-nowrap">{{ $currency($po->total_amount) }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex rounded-md border px-2.5 py-0.5 text-[11px] font-semibold {{ $po->status === 'received' ? 'bg-money-50 border-money-200 text-money-700' : 'bg-accent-50 border-accent-200 text-accent-700' }}">
                                        {{ $po->status === 'received' ? 'Recebida' : 'Aberta' }}
                                    </span>
                                    @if ($po->invoice_number)
                                        <span class="block font-mono text-[10px] text-slate-500 mt-1">NF {{ $po->invoice_number }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    @if ($po->status !== 'received' && $canManagePurchasing)
                                        <form method="POST" action="{{ route('admin.purchase-orders.receive', $po) }}" class="inline-flex items-center justify-end gap-1.5">
                                            @csrf
                                            @method('PATCH')
                                            <label for="nf_{{ $po->id }}" class="sr-only">Número da NF-e</label>
                                            <input id="nf_{{ $po->id }}" type="text" name="invoice_number" required placeholder="NF-e"
                                                   class="w-24 rounded-lg border-line bg-paper px-2.5 py-1.5 font-mono text-[11px] text-ink focus:border-ink focus:ring-ink">
                                            <input type="hidden" name="update_product_cost" value="1">
                                            <button type="submit" class="rounded-lg bg-money-600 hover:bg-money-700 px-3 py-1.5 font-semibold text-white transition whitespace-nowrap">
                                                Receber no Estoque
                                            </button>
                                        </form>
                                    @elseif ($po->status === 'received')
                                        <span class="font-mono text-slate-500 tabular-nums">{{ $po->received_at?->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-slate-400">Aguardando compras</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Nenhuma ordem de compra registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $purchaseOrders->links() }}</div>

        {{-- Suppliers & Stock Movements --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h2 class="font-serif text-xl text-ink">Fornecedores Homologados</h2>
                    <span class="font-mono text-xs text-slate-500">{{ $suppliers->count() }} ativo(s)</span>
                </div>
                <div class="mt-4 space-y-3 text-xs">
                    @forelse ($suppliers as $supplier)
                        <div class="rounded-lg border border-line bg-paper/60 p-3.5 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold text-ink">{{ $supplier->trade_name ?: $supplier->legal_name }}</p>
                                <span class="font-mono text-[11px] font-semibold text-accent-700 tabular-nums">{{ $supplier->purchase_orders_count }} OCs</span>
                            </div>
                            <p class="text-[11px] text-slate-600">
                                <span class="font-mono">{{ $supplier->cnpj }}</span> · Prazo: {{ $supplier->lead_time_days }} dias · {{ $supplier->payment_terms ?: 'A combinar' }}
                            </p>
                            @if ($supplier->supplierProducts->isNotEmpty())
                                <div class="pt-1.5 border-t border-line/70 flex flex-wrap gap-1.5">
                                    @foreach ($supplier->supplierProducts->take(4) as $sp)
                                        <span class="rounded bg-surface border border-line px-2 py-0.5 text-[10px] text-slate-700">
                                            {{ $sp->product?->name }} ({{ $currency($sp->supplier_cost) }})
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-slate-500">Nenhum fornecedor cadastrado.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h2 class="font-serif text-xl text-ink">Movimentações Recentes de Estoque</h2>
                    <span class="text-xs text-slate-500">Últimos 25 lançamentos</span>
                </div>
                <div class="mt-4 space-y-2 text-xs max-h-[420px] overflow-y-auto pr-1">
                    @forelse ($stockMovements as $movement)
                        @php
                            $movementLabel = match ($movement->type) {
                                \App\Models\StockMovement::TYPE_PURCHASE_IN => 'Entrada Compra',
                                \App\Models\StockMovement::TYPE_SALE_OUT => 'Saída Pedido',
                                \App\Models\StockMovement::TYPE_CANCEL_RETURN => 'Estorno/Cancelamento',
                                default => 'Ajuste Inventário',
                            };
                        @endphp
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-line bg-paper/50 px-3 py-2.5">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink truncate">{{ $movement->product?->name }}</p>
                                <p class="text-[11px] text-slate-500 truncate">
                                    {{ $movementLabel }} · {{ $movement->notes }}
                                    @if($movement->user) · {{ $movement->user->name }} @endif
                                </p>
                            </div>
                            <span class="font-mono font-bold tabular-nums shrink-0 {{ $movement->quantity >= 0 ? 'text-money-700' : 'text-danger-700' }}">
                                {{ $movement->quantity >= 0 ? '+' : '' }}{{ $movement->quantity }}
                            </span>
                        </div>
                    @empty
                        <p class="text-slate-500">Sem movimentações registradas.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Modal: New Supplier --}}
        <div x-show="supplierModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="modal-supplier-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="supplierModalOpen = false" class="w-full max-w-lg rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h2 id="modal-supplier-title" class="font-serif text-xl text-ink">Cadastrar Fornecedor</h2>
                    <button type="button" data-compact-btn @click="supplierModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.suppliers.store') }}" class="grid gap-3 sm:grid-cols-2 text-xs">
                    @csrf
                    <div class="sm:col-span-2">
                        <label for="sup_legal" class="block font-semibold text-ink mb-1">Razão Social</label>
                        <input id="sup_legal" type="text" name="legal_name" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="sup_cnpj" class="block font-semibold text-ink mb-1">CNPJ</label>
                        <input id="sup_cnpj" type="text" name="cnpj" required placeholder="00.000.000/0001-00" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="sup_trade" class="block font-semibold text-ink mb-1">Nome Fantasia</label>
                        <input id="sup_trade" type="text" name="trade_name" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="sup_contact" class="block font-semibold text-ink mb-1">Contato</label>
                        <input id="sup_contact" type="text" name="contact_name" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="sup_email" class="block font-semibold text-ink mb-1">E-mail Comercial</label>
                        <input id="sup_email" type="email" name="email" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="sup_terms" class="block font-semibold text-ink mb-1">Condição de Pagamento</label>
                        <input id="sup_terms" type="text" name="payment_terms" placeholder="Ex.: 28 ddl boleto" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="sup_lead" class="block font-semibold text-ink mb-1">Prazo de Entrega (dias)</label>
                        <input id="sup_lead" type="number" name="lead_time_days" min="1" value="5" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-line">
                        <button type="button" @click="supplierModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 font-semibold text-ink">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper">Salvar Fornecedor</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: New Purchase Order --}}
        <div x-show="poModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="modal-po-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="poModalOpen = false" class="w-full max-w-lg rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h2 id="modal-po-title" class="font-serif text-xl text-ink">Nova Ordem de Compra</h2>
                    <button type="button" data-compact-btn @click="poModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.purchase-orders.store') }}" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label for="po_supplier" class="block font-semibold text-ink mb-1">Fornecedor</label>
                        <select id="po_supplier" name="supplier_id" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            <option value="">Selecione o fornecedor...</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->trade_name ?: $supplier->legal_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="po_product" class="block font-semibold text-ink mb-1">Insumo / SKU</label>
                        <select id="po_product" name="product_id" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            <option value="">Selecione o produto...</option>
                            @foreach ($products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->sku ? $prod->sku . ' · ' : '' }}{{ $prod->name }} (Estoque: {{ $prod->stock }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="po_qty" class="block font-semibold text-ink mb-1">Quantidade</label>
                            <input id="po_qty" type="number" name="quantity" min="1" x-model.number="poQty" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                        <div>
                            <label for="po_cost" class="block font-semibold text-ink mb-1">Custo Unit. (R$)</label>
                            <input id="po_cost" type="number" name="unit_cost" step="0.01" min="0.01" x-model.number="poCost" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                        <div>
                            <label for="po_freight" class="block font-semibold text-ink mb-1">Frete Total (R$)</label>
                            <input id="po_freight" type="number" name="freight_amount" step="0.01" min="0" x-model.number="poFreight" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                    </div>
                    <div class="rounded-lg bg-paper border border-line px-3 py-2 flex items-center justify-between">
                        <span class="text-slate-600">Total estimado da OC (com frete):</span>
                        <strong class="font-mono text-sm text-ink tabular-nums" x-text="'R$ ' + poTotalPreview.toFixed(2).replace('.', ',')"></strong>
                    </div>
                    <div>
                        <label for="po_notes" class="block font-semibold text-ink mb-1">Observações da compra</label>
                        <input id="po_notes" type="text" name="notes" placeholder="Opcional" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-line">
                        <button type="button" @click="poModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 font-semibold text-ink">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper">Emitir Ordem de Compra</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Link Supplier Product Quote --}}
        <div x-show="linkModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="modal-link-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="linkModalOpen = false" class="w-full max-w-lg rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h2 id="modal-link-title" class="font-serif text-xl text-ink">Vincular Cotação Fornecedor–SKU</h2>
                    <button type="button" data-compact-btn @click="linkModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.suppliers.link-product') }}" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label for="link_supplier" class="block font-semibold text-ink mb-1">Fornecedor</label>
                        <select id="link_supplier" name="supplier_id" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->trade_name ?: $supplier->legal_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="link_product" class="block font-semibold text-ink mb-1">Produto do Catálogo</label>
                        <select id="link_product" name="product_id" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            @foreach ($products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->sku ? $prod->sku . ' · ' : '' }}{{ $prod->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="link_sku" class="block font-semibold text-ink mb-1">SKU Fornecedor</label>
                            <input id="link_sku" type="text" name="supplier_sku" placeholder="Opcional" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                        <div>
                            <label for="link_cost" class="block font-semibold text-ink mb-1">Custo Cotado (R$)</label>
                            <input id="link_cost" type="number" name="supplier_cost" step="0.01" min="0.01" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                        <div>
                            <label for="link_lead" class="block font-semibold text-ink mb-1">Prazo (dias)</label>
                            <input id="link_lead" type="number" name="lead_time_days" min="1" value="3" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-line">
                        <button type="button" @click="linkModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 font-semibold text-ink">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper">Salvar Cotação</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Inventory Adjustment --}}
        <div x-show="adjustModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="modal-adjust-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="adjustModalOpen = false" class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h2 id="modal-adjust-title" class="font-serif text-xl text-ink">Ajuste Manual de Estoque</h2>
                    <button type="button" data-compact-btn @click="adjustModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" :action="'{{ url('admin/estoque') }}/' + adjustProductId + '/ajuste'" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label for="adj_product" class="block font-semibold text-ink mb-1">Produto</label>
                        <select id="adj_product" x-model="adjustProductId" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            @foreach ($products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->name }} (Atual: {{ $prod->stock }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="adj_delta" class="block font-semibold text-ink mb-1">Variação (+/-)</label>
                        <input id="adj_delta" type="number" name="quantity_delta" required placeholder="Ex.: 10 para entrada ou -2 para baixa"
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="adj_notes" class="block font-semibold text-ink mb-1">Justificativa do Inventário</label>
                        <input id="adj_notes" type="text" name="notes" required placeholder="Ex.: Contagem cíclica mensal"
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-line">
                        <button type="button" @click="adjustModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 font-semibold text-ink">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper">Registrar Ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
