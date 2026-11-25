@php
    $currency = static fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $operator = auth()->user();
    $canManageCatalog = (bool) $operator?->canManageCatalog();
    $canManageFinance = (bool) $operator?->canManageFinance();
    $canManageOrders = (bool) $operator?->canManageOrders();
    $isFullAdmin = (bool) $operator?->is_admin || $operator?->roleEnum() === \App\Enums\UserRole::Admin;
    $queueTotalCount = $pendingPaymentsCount + $pendingOrdersCount + $pendingQuestions->count();
@endphp

<x-admin-layout title="Visão Geral & Operação">
    <div x-data="{
            activeTab: @js($activeTab),
            auditQuery: '',
            rejectModalOpen: false,
            rejectAction: '',
            rejectCompany: '',
            confirmModalOpen: false,
            confirmTitle: '',
            confirmMessage: '',
            confirmAction: '',
            confirmButtonText: 'Confirmar',
            createProductModalOpen: false,
            createCategoryModalOpen: false,
            editProductModalOpen: false,
            editProductAction: '',
            createProduct: {
                cost_price: 0,
                inbound_freight: 0,
                other_costs: 0,
                markup_percent: 35,
                das_rate: 4
            },
            editProduct: {
                name: '',
                sku: '',
                brand: 'NexoSupri',
                unit: 'un',
                presentation: '',
                category_id: '',
                cost_price: 0,
                inbound_freight: 0,
                other_costs: 0,
                markup_percent: 35,
                das_rate: 4,
                min_stock: 5,
                stock: 0,
                description: ''
            },
            simCredits: {{ (float) $simCredits }},
            simMarkup: {{ (float) $simMarkup }},
            simDas: {{ (float) $simDas }},
            initialSim: @js([
                'credits' => (float) $simCredits,
                'markup' => (float) $simMarkup,
                'das' => (float) $simDas,
                'credits_available' => (float) $portfolioSimulation['credits_available'],
                'simulated_sales' => (float) $portfolioSimulation['simulated_sales'],
                'merchandise_and_freight_cost' => (float) $portfolioSimulation['merchandise_and_freight_cost'],
                'estimated_das' => (float) $portfolioSimulation['estimated_das'],
                'result_after_cost_and_das' => (float) $portfolioSimulation['result_after_cost_and_das'],
                'estimated_margin_percent' => (float) $portfolioSimulation['estimated_margin_percent'],
            ]),
            init() {
                if (window.location.hash === '#produtos') {
                    this.activeTab = 'catalogo';
                }
            },
            get liveSim() {
                if (
                    Number(this.simCredits) === this.initialSim.credits &&
                    Number(this.simMarkup) === this.initialSim.markup &&
                    Number(this.simDas) === this.initialSim.das
                ) {
                    return this.initialSim;
                }
                const credits = Math.max(500, Number(this.simCredits || 0));
                const m = Math.max(0.01, Number(this.simMarkup || 0)) / 100;
                const t = Math.max(0, Math.min(30, Number(this.simDas || 0))) / 100;
                const denom = Math.max(0.05, 1 - t);
                const sales = Number((credits * 0.998).toFixed(2));
                const cost = Number(((sales * denom) / (1 + m)).toFixed(2));
                const das = Number((sales * t).toFixed(2));
                const net = Number((sales - cost - das).toFixed(2));
                const margin = sales > 0 ? Number(((net / sales) * 100).toFixed(2)) : 0;
                return {
                    credits_available: credits,
                    simulated_sales: sales,
                    merchandise_and_freight_cost: cost,
                    estimated_das: das,
                    result_after_cost_and_das: net,
                    estimated_margin_percent: margin,
                };
            },
            formatMoney(val) {
                return 'R$ ' + Number(val || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            openReject(action, companyName) {
                this.rejectAction = action;
                this.rejectCompany = companyName;
                this.rejectModalOpen = true;
            },
            openDeleteConfirm(action, title, message, buttonText) {
                this.confirmAction = action;
                this.confirmTitle = title;
                this.confirmMessage = message;
                this.confirmButtonText = buttonText || 'Confirmar';
                this.confirmModalOpen = true;
            },
            openEditProduct(product, action) {
                this.editProductAction = action;
                this.editProduct = {
                    name: product.name || '',
                    sku: product.sku || '',
                    brand: product.brand || 'NexoSupri',
                    unit: product.unit || 'un',
                    presentation: product.presentation || '',
                    category_id: product.category_id || '',
                    cost_price: product.cost_price || 0,
                    inbound_freight: product.inbound_freight || 0,
                    other_costs: product.other_costs || 0,
                    markup_percent: product.markup_percent || 35,
                    das_rate: product.das_rate || 4,
                    min_stock: product.min_stock || 5,
                    stock: product.stock || 0,
                    description: product.description || ''
                };
                this.editProductModalOpen = true;
            },
            simulatedSalePrice(item) {
                const c = Number(item.cost_price || 0) + Number(item.inbound_freight || 0) + Number(item.other_costs || 0);
                const m = Number(item.markup_percent || 0) / 100;
                const t = Number(item.das_rate || 0) / 100;
                const denom = Math.max(0.05, 1 - t);
                return (c * (1 + m)) / denom;
            }
         }"
         @keydown.escape.window="rejectModalOpen = false; confirmModalOpen = false; createProductModalOpen = false; createCategoryModalOpen = false; editProductModalOpen = false"
         class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end justify-between border-b border-line pb-5">
            <div>
                <x-eyebrow>Operação B2B · Modelo A (1 crédito = R$ 1,00)</x-eyebrow>
                <h1 class="mt-1 font-serif text-2xl sm:text-3xl text-ink">Painel Executivo &amp; Operacional</h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600 max-w-2xl">
                    Acompanhe carteira de créditos, fila de aprovações, estoque crítico, precificação com DAS/markup e governança por perfil.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('admin.reports.sales.csv') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-3.5 py-2 text-xs font-semibold text-ink transition">
                    <x-icon name="file-text" class="size-3.5 text-accent-600" />
                    <span>Exportar Vendas (CSV)</span>
                </a>
                <a href="{{ route('admin.reports.credits.csv') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-3.5 py-2 text-xs font-semibold text-ink transition">
                    <x-icon name="wallet" class="size-3.5 text-money-600" />
                    <span>Exportar Créditos (CSV)</span>
                </a>
                @if ($canManageCatalog)
                    <button type="button" @click="createProductModalOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 text-xs font-semibold text-paper transition">
                        <x-icon name="plus" class="size-3.5" />
                        <span>Novo Produto</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Accessible IHC Tab Bar (Reduces cognitive load across 4 operational domains) --}}
        <div class="bg-surface rounded-xl border border-line p-2 flex flex-wrap items-center gap-1.5"
             role="tablist" aria-label="Seções do painel administrativo">
            <button type="button"
                    id="tab-visao"
                    role="tab"
                    aria-controls="panel-visao"
                    :aria-selected="activeTab === 'visao' ? 'true' : 'false'"
                    @click="activeTab = 'visao'"
                    :class="activeTab === 'visao' ? 'bg-ink text-paper shadow-sm' : 'text-slate-700 hover:bg-paper hover:text-ink'"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition">
                <x-icon name="trending-up" class="size-4 shrink-0" />
                <span>Visão Executiva</span>
            </button>

            <button type="button"
                    id="tab-fila"
                    role="tab"
                    aria-controls="panel-fila"
                    :aria-selected="activeTab === 'fila' ? 'true' : 'false'"
                    @click="activeTab = 'fila'"
                    :class="activeTab === 'fila' ? 'bg-ink text-paper shadow-sm' : 'text-slate-700 hover:bg-paper hover:text-ink'"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition">
                <x-icon name="bell" class="size-4 shrink-0" />
                <span>Fila Operacional</span>
                @if ($queueTotalCount > 0)
                    <span :class="activeTab === 'fila' ? 'bg-accent-500 text-paper' : 'bg-accent-50 text-accent-700 border border-accent-200'"
                          class="px-2 py-0.5 rounded-full font-mono text-[10px] tabular-nums font-bold">
                        {{ $queueTotalCount }}
                    </span>
                @endif
            </button>

            <button type="button"
                    id="tab-catalogo"
                    role="tab"
                    aria-controls="panel-catalogo"
                    :aria-selected="activeTab === 'catalogo' ? 'true' : 'false'"
                    @click="activeTab = 'catalogo'"
                    :class="activeTab === 'catalogo' ? 'bg-ink text-paper shadow-sm' : 'text-slate-700 hover:bg-paper hover:text-ink'"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition">
                <x-icon name="tag" class="size-4 shrink-0" />
                <span>Catálogo &amp; Preços</span>
                <span :class="activeTab === 'catalogo' ? 'text-slate-300' : 'text-slate-500'"
                      class="font-mono text-[11px] tabular-nums">({{ $products->total() }})</span>
            </button>

            <button type="button"
                    id="tab-governanca"
                    role="tab"
                    aria-controls="panel-governanca"
                    :aria-selected="activeTab === 'governanca' ? 'true' : 'false'"
                    @click="activeTab = 'governanca'"
                    :class="activeTab === 'governanca' ? 'bg-ink text-paper shadow-sm' : 'text-slate-700 hover:bg-paper hover:text-ink'"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition">
                <x-icon name="shield-check" class="size-4 shrink-0" />
                <span>Simulador DAS &amp; Governança</span>
            </button>
        </div>

        {{-- TAB 1: Visão Executiva --}}
        <div id="panel-visao" role="tabpanel" aria-labelledby="tab-visao"
             x-show="activeTab === 'visao'" @if($activeTab !== 'visao') x-cloak @endif
             class="space-y-6">

            {{-- Primary KPIs --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Empresas Ativas</span>
                        <x-icon name="layers" class="size-4 text-accent-600" />
                    </div>
                    <p class="mt-2 font-mono text-2xl font-bold text-ink tabular-nums">{{ $activeCompaniesCount }}</p>
                    <p class="mt-1 text-xs text-slate-600">
                        Em aberto: <strong class="font-mono text-ink tabular-nums">{{ $currency($openCreditBalance) }}</strong>
                        @if ($lowStockCompaniesCount > 0)
                            · <a href="{{ route('admin.companies.index', ['status' => 'low_balance']) }}" class="text-accent-700 font-semibold hover:underline">{{ $lowStockCompaniesCount }} saldo baixo</a>
                        @endif
                    </p>
                </div>

                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Créditos Vendidos</span>
                        <x-icon name="wallet" class="size-4 text-money-600" />
                    </div>
                    <p class="mt-2 font-mono text-2xl font-bold text-ink tabular-nums">{{ $currency($creditsSold) }}</p>
                    <div class="mt-2 space-y-1">
                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                            <span>Utilizados: <strong class="font-mono text-money-700">{{ $currency($creditsUsed) }}</strong></span>
                            <span class="font-mono font-semibold text-ink tabular-nums">{{ number_format($creditUtilizationRate, 1, ',', '.') }}%</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-paper overflow-hidden">
                            <div class="h-full rounded-full bg-money-600" style="width: {{ min(100, max(0, $creditUtilizationRate)) }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Vendas em Produtos</span>
                        <x-icon name="trending-up" class="size-4 text-ink" />
                    </div>
                    <p class="mt-2 font-mono text-2xl font-bold text-ink tabular-nums">{{ $currency($totalSales) }}</p>
                    <p class="mt-1 text-xs text-slate-600">
                        Ticket médio: <strong class="font-mono text-ink tabular-nums">{{ $currency($ticketAverage) }}</strong> · Entregues: <span class="font-mono">{{ $deliveredOrdersCount }}</span>
                    </p>
                </div>

                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Resultado &amp; DAS Estimado</span>
                        <x-icon name="scale" class="size-4 text-money-600" />
                    </div>
                    <p class="mt-2 font-mono text-2xl font-bold text-money-700 tabular-nums">{{ $currency($estimatedMarginTotal) }}</p>
                    <p class="mt-1 text-xs text-slate-600">
                        DAS estimado: <strong class="font-mono text-ink tabular-nums">{{ $currency($estimatedDasTotal) }}</strong>
                    </p>
                </div>
            </div>

            {{-- Actionable Operational Alerts --}}
            <div class="grid gap-4 md:grid-cols-3">
                <a href="{{ route('admin.payments.index') }}"
                   class="flex items-center justify-between rounded-xl border border-accent-200 bg-surface hover:border-ink p-4 transition shadow-sm group">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-accent-700">Pagamentos Pendentes</p>
                        <p class="mt-1 font-mono text-xl font-bold text-ink tabular-nums">{{ $pendingPaymentsCount }}</p>
                        <p class="text-xs text-slate-600">Aguardando conferência: <strong class="font-mono">{{ $currency($pendingPaymentsAmount) }}</strong></p>
                    </div>
                    <span class="h-10 w-10 rounded-lg bg-accent-50 border border-accent-200 text-accent-700 flex items-center justify-center shrink-0 group-hover:bg-ink group-hover:text-paper group-hover:border-ink transition">
                        <x-icon name="qr-code" class="size-5" />
                    </span>
                </a>

                <a href="{{ route('admin.orders.index') }}"
                   class="flex items-center justify-between rounded-xl border border-line bg-surface hover:border-ink p-4 transition shadow-sm group">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Pedidos em Operação</p>
                        <p class="mt-1 font-mono text-xl font-bold text-ink tabular-nums">{{ $pendingOrdersCount }}</p>
                        <p class="text-xs text-slate-600">Solicitados, em separação ou em trânsito</p>
                    </div>
                    <span class="h-10 w-10 rounded-lg bg-paper border border-line text-ink flex items-center justify-center shrink-0 group-hover:bg-ink group-hover:text-paper transition">
                        <x-icon name="truck" class="size-5" />
                    </span>
                </a>

                <a href="{{ route('admin.suppliers.index') }}"
                   class="flex items-center justify-between rounded-xl border {{ $lowStockProducts->isNotEmpty() ? 'border-danger-200' : 'border-line' }} bg-surface hover:border-ink p-4 transition shadow-sm group">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide {{ $lowStockProducts->isNotEmpty() ? 'text-danger-700' : 'text-slate-600' }}">Alertas de Estoque Mínimo</p>
                        <p class="mt-1 font-mono text-xl font-bold {{ $lowStockProducts->isNotEmpty() ? 'text-danger-700' : 'text-ink' }} tabular-nums">{{ $lowStockProducts->count() }}</p>
                        <p class="text-xs text-slate-600">Itens no limite ou abaixo do mínimo</p>
                    </div>
                    <span class="h-10 w-10 rounded-lg {{ $lowStockProducts->isNotEmpty() ? 'bg-danger-50 border-danger-200 text-danger-700' : 'bg-paper border-line text-ink' }} border flex items-center justify-center shrink-0 group-hover:bg-ink group-hover:text-paper group-hover:border-ink transition">
                        <x-icon name="alert-triangle" class="size-5" />
                    </span>
                </a>
            </div>

            {{-- Charts Row --}}
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="font-serif text-xl text-ink">Créditos Vendidos vs Vendas de Produtos (6 meses)</h2>
                            <p class="text-xs text-slate-500">Comparativo mensal de recargas empresariais aprovadas e consumo convertido no catálogo.</p>
                        </div>
                        <div class="flex items-center gap-4 text-xs">
                            <span class="inline-flex items-center gap-1.5 text-slate-700 font-medium">
                                <span class="h-2.5 w-2.5 rounded-sm bg-money-600"></span> Créditos
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-slate-700 font-medium">
                                <span class="h-2.5 w-2.5 rounded-sm bg-accent-600"></span> Vendas
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-6 items-end gap-3 h-44 border-b border-line pb-2">
                        @foreach ($monthlyChart as $point)
                            <div class="flex h-full flex-col items-center justify-end gap-1.5">
                                <div class="flex h-full w-full items-end justify-center gap-1.5">
                                    <div class="w-4 rounded-t-sm bg-money-600 transition-all"
                                         style="height: {{ max(4, (int) $point['credits_pct']) }}%"
                                         title="Créditos: {{ $currency($point['credits']) }}"></div>
                                    <div class="w-4 rounded-t-sm bg-accent-600 transition-all"
                                         style="height: {{ max(4, (int) $point['sales_pct']) }}%"
                                         title="Vendas: {{ $currency($point['sales']) }}"></div>
                                </div>
                                <span class="font-mono text-[11px] font-medium text-slate-600">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2 text-xs">
                        @foreach ($monthlyChart as $point)
                            <div class="rounded-lg border border-line bg-paper/70 p-2.5">
                                <p class="font-mono font-semibold text-ink">{{ $point['label'] }}</p>
                                <p class="text-[11px] text-money-700 font-mono tabular-nums mt-0.5">Cr: {{ $currency($point['credits']) }}</p>
                                <p class="text-[11px] text-accent-700 font-mono tabular-nums">Vd: {{ $currency($point['sales']) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                        <h2 class="font-serif text-xl text-ink">Vendas por Categoria</h2>
                        <div class="mt-4 space-y-3">
                            @forelse ($salesByDepartment as $dept)
                                <div>
                                    <div class="flex items-center justify-between text-xs gap-2">
                                        <span class="font-medium text-slate-700 truncate">{{ $dept['name'] }}</span>
                                        <span class="font-mono font-semibold text-ink tabular-nums shrink-0">{{ $currency($dept['revenue']) }} ({{ $dept['percent'] }}%)</span>
                                    </div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-paper">
                                        <div class="h-full rounded-full bg-accent-600" style="width: {{ min(100, max(3, (float) $dept['percent'])) }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">Ainda não há vendas por categoria registradas.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                        <h2 class="font-serif text-xl text-ink">Pedidos por Status</h2>
                        <div class="mt-3 space-y-1.5">
                            @forelse ($ordersByStatus as $st)
                                <div class="flex items-center justify-between rounded-lg border border-line bg-paper/60 px-3 py-2 text-xs">
                                    <span class="font-medium text-slate-700">{{ $st['label'] }}</span>
                                    <span class="rounded-md bg-surface border border-line px-2 py-0.5 font-mono font-bold text-ink tabular-nums">{{ $st['count'] }}</span>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">Sem pedidos registrados.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Top Products & Low Stock --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <h2 class="font-serif text-xl text-ink">Top Produtos Mais Consumidos</h2>
                    <div class="mt-3 table-scroll-region">
                        <table class="min-w-full divide-y divide-line text-xs">
                            <thead>
                                <tr class="text-left text-slate-500">
                                    <th scope="col" class="pb-2 font-semibold">Produto</th>
                                    <th scope="col" class="pb-2 text-right font-semibold">Qtd</th>
                                    <th scope="col" class="pb-2 text-right font-semibold">Receita</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse ($topProducts as $item)
                                    <tr>
                                        <td class="py-2.5 font-medium text-ink">{{ $item->product_name }}</td>
                                        <td class="py-2.5 text-right font-mono text-slate-600 tabular-nums">{{ $item->total_units }}</td>
                                        <td class="py-2.5 text-right font-mono font-semibold text-ink tabular-nums">{{ $currency($item->total_revenue) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-slate-500">Ainda não há histórico de consumo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-serif text-xl text-ink">Itens em Estoque Crítico</h2>
                        <div class="flex items-center gap-3 text-xs">
                            <a href="{{ route('admin.dashboard', ['tab' => 'catalogo', 'low_stock' => 1]) }}" class="font-semibold text-accent-600 hover:underline">Filtrar catálogo</a>
                            <a href="{{ route('admin.suppliers.index') }}" class="font-semibold text-ink hover:underline">Repor estoque →</a>
                        </div>
                    </div>
                    <div class="mt-3 table-scroll-region">
                        <table class="min-w-full divide-y divide-line text-xs">
                            <thead>
                                <tr class="text-left text-slate-500">
                                    <th scope="col" class="pb-2 font-semibold">SKU / Produto</th>
                                    <th scope="col" class="pb-2 text-right font-semibold">Atual</th>
                                    <th scope="col" class="pb-2 text-right font-semibold">Mínimo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse ($lowStockProducts as $low)
                                    <tr>
                                        <td class="py-2.5 font-medium text-ink">{{ $low->sku ? $low->sku . ' · ' : '' }}{{ $low->name }}</td>
                                        <td class="py-2.5 text-right font-mono font-bold text-danger-700 tabular-nums">{{ $low->stock }}</td>
                                        <td class="py-2.5 text-right font-mono text-slate-600 tabular-nums">{{ $low->min_stock }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-money-700 font-medium">Todos os itens estão acima do estoque mínimo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 2: Fila Operacional --}}
        <div id="panel-fila" role="tabpanel" aria-labelledby="tab-fila"
             x-show="activeTab === 'fila'" @if($activeTab !== 'fila') x-cloak @endif
             class="space-y-6">

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Pending Payments --}}
                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between border-b border-line pb-3">
                        <h2 class="font-serif text-xl text-ink">Pagamentos Aguardando Aprovação</h2>
                        <a href="{{ route('admin.payments.index') }}" class="text-xs font-semibold text-accent-600 hover:underline">Ver todos →</a>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($pendingPayments as $payment)
                            @php
                                $payCompanyName = $payment->company?->trade_name ?? $payment->company?->legal_name ?? 'Empresa';
                            @endphp
                            <div class="rounded-lg border border-line bg-paper/50 p-3.5">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-ink">{{ $payCompanyName }}</p>
                                        <p class="text-xs text-slate-600 mt-0.5">
                                            Plano: {{ $payment->contract?->plan?->name ?? 'Avulso' }} · {{ strtoupper((string) $payment->payment_method) }} · {{ $payment->requestedBy?->name ?? 'Sistema' }}
                                        </p>
                                    </div>
                                    <span class="font-mono text-sm font-bold text-ink tabular-nums">{{ $currency($payment->amount) }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    @if ($canManageFinance)
                                        <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-money-600 hover:bg-money-700 px-3 py-1.5 text-xs font-semibold text-white transition">
                                                <x-icon name="check" class="size-3.5" />
                                                <span>Aprovar &amp; Creditar</span>
                                            </button>
                                        </form>
                                        <button type="button"
                                                @click="openReject('{{ route('admin.payments.reject', $payment) }}', '{{ addslashes($payCompanyName) }}')"
                                                class="inline-flex items-center gap-1 rounded-lg border border-danger-200 bg-danger-50 hover:bg-danger-100 px-3 py-1.5 text-xs font-semibold text-danger-700 transition">
                                            <x-icon name="x" class="size-3.5" />
                                            <span>Rejeitar</span>
                                        </button>
                                    @else
                                        <span class="text-[11px] text-slate-500">Somente Financeiro / Admin</span>
                                    @endif

                                    @if ($payment->receipt_path)
                                        <a href="{{ route('payments.receipt', $payment) }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 rounded-lg border border-line bg-surface hover:border-ink px-3 py-1.5 text-xs font-medium text-ink transition">
                                            <x-icon name="file-text" class="size-3.5" />
                                            <span>Comprovante</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="rounded-lg border border-line bg-paper p-5 text-xs text-slate-500 text-center">Nenhum pagamento pendente de revisão.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Pending Orders --}}
                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between border-b border-line pb-3">
                        <h2 class="font-serif text-xl text-ink">Pedidos Recentes em Operação</h2>
                        <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-accent-600 hover:underline">Gerenciar pedidos →</a>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($pendingOrders as $order)
                            @php
                                $orderNum = '#PED-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                                $orderCompanyName = $order->company?->trade_name ?? $order->company?->legal_name ?? $order->user?->name ?? 'Empresa';
                            @endphp
                            <div class="rounded-lg border border-line bg-paper/50 p-3.5">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-ink">
                                            <span class="font-mono">{{ $orderNum }}</span> · {{ $orderCompanyName }}
                                        </p>
                                        <p class="text-xs text-slate-600 mt-0.5">
                                            Status: <span class="font-semibold text-ink">{{ $order->statusEnum()->label() }}</span>
                                            · {{ $order->items->count() }} {{ $order->items->count() === 1 ? 'item' : 'itens' }}
                                        </p>
                                    </div>
                                    <span class="font-mono text-sm font-bold text-ink tabular-nums">{{ $currency($order->total_amount) }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <a href="{{ route('orders.show', $order) }}"
                                       class="inline-flex items-center gap-1 rounded-lg border border-line bg-surface hover:border-ink px-3 py-1.5 text-xs font-semibold text-ink transition">
                                        <x-icon name="eye" class="size-3.5" />
                                        <span>Detalhes</span>
                                    </a>
                                    @if ($canManageOrders && in_array($order->status, [\App\Models\Order::STATUS_REQUESTED, \App\Models\Order::STATUS_SEPARATING, \App\Models\Order::STATUS_SHIPPED], true))
                                        <form method="POST" action="{{ route('admin.orders.deliver', $order) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-ink hover:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-paper transition">
                                                <x-icon name="truck" class="size-3.5" />
                                                <span>Confirmar Entrega</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="rounded-lg border border-line bg-paper p-5 text-xs text-slate-500 text-center">Nenhum pedido aguardando expedição.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Pending Questions --}}
            <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                <h2 class="font-serif text-xl text-ink border-b border-line pb-3">Dúvidas Pendentes no Catálogo</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2 text-xs">
                    @forelse ($pendingQuestions as $question)
                        <div class="rounded-lg border border-line bg-paper/50 p-3.5 space-y-2">
                            <p class="font-semibold text-ink">{{ $question->product?->name }}</p>
                            <p class="text-slate-700">“{{ $question->question }}”</p>
                            <form method="POST" action="{{ route('admin.questions.answer', $question) }}" class="flex gap-2">
                                @csrf
                                @method('PATCH')
                                <label for="answer-{{ $question->id }}" class="sr-only">Responder dúvida sobre {{ $question->product?->name }}</label>
                                <input id="answer-{{ $question->id }}" type="text" name="answer" required placeholder="Escreva a resposta técnica..."
                                       class="flex-1 rounded-lg border-line bg-surface px-3 py-1.5 text-xs text-ink focus:border-ink focus:ring-ink">
                                <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-1.5 font-semibold text-paper transition">
                                    Responder
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="md:col-span-2 rounded-lg border border-line bg-paper p-5 text-slate-500 text-center">Nenhuma dúvida pendente de resposta.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- TAB 3: Catálogo & Preços --}}
        <div id="panel-catalogo" role="tabpanel" aria-labelledby="tab-catalogo"
             x-show="activeTab === 'catalogo'" @if($activeTab !== 'catalogo') x-cloak @endif
             class="space-y-6">

            <div id="produtos" class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between border-b border-line pb-4">
                    <div>
                        <h2 class="font-serif text-xl text-ink">Catálogo B2B &amp; Precificação</h2>
                        <p class="text-xs text-slate-500">Edite custo, frete de entrada, markup e DAS; o preço final em créditos é recalculado automaticamente.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="tab" value="catalogo">
                        <label for="admin_product_q" class="sr-only">Buscar produto ou SKU</label>
                        <input id="admin_product_q" type="search" name="product_q" value="{{ request('product_q') }}" placeholder="Buscar produto ou SKU..."
                               class="rounded-lg border-line bg-paper px-3 py-1.5 text-xs text-ink focus:border-ink focus:ring-ink">
                        <label for="filter_low_stock" class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-paper px-2.5 py-1.5 text-xs text-ink cursor-pointer">
                            <input id="filter_low_stock" type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock'))
                                   class="rounded border-line text-ink focus:ring-ink">
                            <span>Estoque crítico</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-1.5 text-xs font-semibold text-paper transition">
                            Filtrar
                        </button>
                        @if (request()->filled('product_q') || request()->boolean('low_stock'))
                            <a href="{{ route('admin.dashboard', ['tab' => 'catalogo']) }}" class="rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-ink">
                                Limpar
                            </a>
                        @endif
                    </form>
                </div>

                <div class="mt-4 table-scroll-region">
                    <table class="min-w-full divide-y divide-line text-xs">
                        <thead>
                            <tr class="text-left text-slate-500">
                                <th scope="col" class="pb-2.5 font-semibold">SKU / Produto</th>
                                <th scope="col" class="pb-2.5 font-semibold">Categoria</th>
                                <th scope="col" class="pb-2.5 text-right font-semibold">Custo Total</th>
                                <th scope="col" class="pb-2.5 text-right font-semibold">Markup / DAS</th>
                                <th scope="col" class="pb-2.5 text-right font-semibold">Preço Venda</th>
                                <th scope="col" class="pb-2.5 text-right font-semibold">Estoque</th>
                                <th scope="col" class="pb-2.5 text-right font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($products as $product)
                                @php
                                    $productPayload = [
                                        'id' => $product->id,
                                        'name' => $product->name,
                                        'sku' => $product->sku,
                                        'brand' => $product->brand,
                                        'unit' => $product->unit,
                                        'presentation' => $product->presentation,
                                        'category_id' => $product->category_id,
                                        'cost_price' => (float) $product->cost_price,
                                        'inbound_freight' => (float) $product->inbound_freight,
                                        'other_costs' => (float) $product->other_costs,
                                        'markup_percent' => (float) $product->markup_percent,
                                        'das_rate' => (float) $product->das_rate,
                                        'min_stock' => (int) $product->min_stock,
                                        'stock' => (int) $product->stock,
                                        'description' => $product->description,
                                    ];
                                @endphp
                                <tr class="hover:bg-paper/45 transition-colors">
                                    <td class="py-2.5 pr-3">
                                        <p class="font-semibold text-ink">{{ $product->name }}</p>
                                        <p class="text-[11px] text-slate-500 font-mono">{{ $product->sku ?? 'S/SKU' }} · {{ $product->presentation ?: $product->unit }}</p>
                                    </td>
                                    <td class="py-2.5 pr-3 text-slate-600">{{ $product->category?->name ?? 'Geral' }}</td>
                                    <td class="py-2.5 text-right font-mono text-slate-700 tabular-nums">{{ $currency($product->total_unit_cost) }}</td>
                                    <td class="py-2.5 text-right font-mono text-slate-600 tabular-nums">
                                        {{ number_format((float) $product->markup_percent, 1, ',', '.') }}% / {{ number_format((float) $product->das_rate, 1, ',', '.') }}%
                                    </td>
                                    <td class="py-2.5 text-right font-mono font-bold text-ink tabular-nums">{{ $currency($product->price) }}</td>
                                    <td class="py-2.5 text-right font-mono tabular-nums {{ $product->is_low_stock ? 'font-bold text-danger-700' : 'text-slate-700' }}">
                                        {{ $product->stock }} (mín {{ $product->min_stock }})
                                    </td>
                                    <td class="py-2.5 text-right">
                                        @if ($canManageCatalog)
                                            <div class="inline-flex items-center gap-1.5">
                                                <button type="button"
                                                        @click='openEditProduct(@json($productPayload), "{{ route('admin.products.update', $product) }}")'
                                                        class="rounded-md border border-line bg-paper hover:border-ink px-2.5 py-1 text-[11px] font-semibold text-ink transition">
                                                    Editar
                                                </button>
                                                <form method="POST" action="{{ route('admin.products.toggle', $product) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            class="rounded-md border px-2.5 py-1 text-[11px] font-semibold transition {{ $product->is_active ? 'bg-money-50 border-money-200 text-money-700 hover:bg-money-100' : 'bg-paper border-line text-slate-600 hover:border-ink' }}">
                                                        {{ $product->is_active ? 'Ativo' : 'Inativo' }}
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-[11px] text-slate-400">Somente leitura</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-500">Nenhum produto encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            </div>

            {{-- Categories & Coupons --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between border-b border-line pb-3">
                        <h2 class="font-serif text-xl text-ink">Categorias do Catálogo</h2>
                        @if ($canManageCatalog)
                            <button type="button" @click="createCategoryModalOpen = true"
                                    class="rounded-lg bg-ink hover:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-paper transition">
                                + Nova Categoria
                            </button>
                        @endif
                    </div>
                    <div class="mt-3 space-y-2 text-xs max-h-96 overflow-y-auto pr-1">
                        @foreach ($categories as $category)
                            <div class="flex items-center justify-between rounded-lg border border-line bg-paper/60 px-3 py-2">
                                <div>
                                    <p class="font-semibold text-ink">{{ $category->name }}</p>
                                    <p class="text-[11px] font-mono text-slate-500">{{ $category->products_count }} produto(s)</p>
                                </div>
                                @if ($canManageCatalog && $category->products_count === 0 && $category->children_count === 0)
                                    <button type="button"
                                            @click="openDeleteConfirm('{{ route('admin.categories.destroy', $category) }}', 'Remover Categoria', 'Confirma a exclusão da categoria {{ addslashes($category->name) }}?', 'Remover Categoria')"
                                            class="text-xs font-semibold text-danger-700 hover:underline">
                                        Remover
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm space-y-4">
                    <div class="border-b border-line pb-3">
                        <h2 class="font-serif text-xl text-ink">Cupons &amp; Condições Comerciais</h2>
                    </div>
                    @if ($canManageCatalog)
                        <form method="POST" action="{{ route('admin.coupons.store') }}" class="grid grid-cols-2 gap-2.5 text-xs">
                            @csrf
                            <div>
                                <label for="coupon_code" class="block font-semibold text-ink mb-1">Código</label>
                                <input id="coupon_code" type="text" name="code" placeholder="EX.: EMPRESA10" required
                                       class="w-full rounded-lg border-line bg-paper px-3 py-1.5 font-mono uppercase text-ink focus:border-ink focus:ring-ink">
                            </div>
                            <div>
                                <label for="coupon_type" class="block font-semibold text-ink mb-1">Tipo</label>
                                <select id="coupon_type" name="type" class="w-full rounded-lg border-line bg-paper px-3 py-1.5 text-ink focus:border-ink focus:ring-ink">
                                    <option value="percent">% Desconto</option>
                                    <option value="fixed">R$ Fixo</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label for="coupon_label" class="block font-semibold text-ink mb-1">Descrição</label>
                                <input id="coupon_label" type="text" name="label" placeholder="Condição comercial para pedidos mensais" required
                                       class="w-full rounded-lg border-line bg-paper px-3 py-1.5 text-ink focus:border-ink focus:ring-ink">
                            </div>
                            <div>
                                <label for="coupon_value" class="block font-semibold text-ink mb-1">Valor</label>
                                <input id="coupon_value" type="number" name="value" step="0.01" min="0.01" required
                                       class="w-full rounded-lg border-line bg-paper px-3 py-1.5 font-mono text-ink focus:border-ink focus:ring-ink">
                            </div>
                            <div>
                                <label for="coupon_min" class="block font-semibold text-ink mb-1">Pedido Mínimo (R$)</label>
                                <input id="coupon_min" type="number" name="min_total" step="0.01" min="0" value="0"
                                       class="w-full rounded-lg border-line bg-paper px-3 py-1.5 font-mono text-ink focus:border-ink focus:ring-ink">
                            </div>
                            <button type="submit" class="col-span-2 rounded-lg bg-ink hover:bg-slate-800 py-2 font-semibold text-paper transition">
                                Salvar Cupom
                            </button>
                        </form>
                    @endif
                    <div class="space-y-2 text-xs">
                        @foreach ($coupons as $coupon)
                            <div class="flex items-center justify-between rounded-lg border border-line bg-paper/60 px-3 py-2">
                                <div>
                                    <span class="font-mono font-bold text-ink">{{ $coupon->code }}</span>
                                    <span class="text-slate-600 font-mono">
                                        · {{ $coupon->type === 'percent' ? $coupon->value . '%' : $currency($coupon->value) }}
                                    </span>
                                </div>
                                @if ($canManageCatalog)
                                    <form method="POST" action="{{ route('admin.coupons.toggle', $coupon) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="font-semibold {{ $coupon->is_active ? 'text-money-700 hover:underline' : 'text-slate-500 hover:underline' }}">
                                            {{ $coupon->is_active ? 'Ativo' : 'Pausado' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 4: Simulador DAS & Governança --}}
        <div id="panel-governanca" role="tabpanel" aria-labelledby="tab-governanca"
             x-show="activeTab === 'governanca'" @if($activeTab !== 'governanca') x-cloak @endif
             class="space-y-6">

            {{-- Live Financial Simulator (README Sections 7, 8 & 9) --}}
            <div class="rounded-xl border border-line bg-surface p-6 shadow-sm space-y-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <x-eyebrow>Formação de Preço B2B · Simples Nacional / DAS</x-eyebrow>
                        <h2 class="mt-1 font-serif text-2xl text-ink">Simulador Financeiro da Carteira de Créditos</h2>
                        <p class="mt-1 text-xs text-slate-600">
                            Fórmula aplicada: <code class="rounded bg-paper border border-line px-1.5 py-0.5 font-mono text-ink">Preço = C × (1 + m) / (1 - t - v)</code>. Ajuste os parâmetros para prévia instantânea ou recalcule com o mix do catálogo.
                        </p>
                    </div>
                    <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-end gap-3 text-xs">
                        <input type="hidden" name="tab" value="governanca">
                        <div>
                            <label for="sim_credits" class="block text-[11px] font-semibold text-ink mb-1">Créditos (R$)</label>
                            <input id="sim_credits" type="number" name="sim_credits" step="50" min="500" x-model.number="simCredits"
                                   class="w-28 rounded-lg border-line bg-paper px-3 py-1.5 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                        <div>
                            <label for="sim_markup" class="block text-[11px] font-semibold text-ink mb-1">Markup (%)</label>
                            <input id="sim_markup" type="number" name="sim_markup" step="0.5" min="1" max="150" x-model.number="simMarkup"
                                   class="w-24 rounded-lg border-line bg-paper px-3 py-1.5 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                        <div>
                            <label for="sim_das" class="block text-[11px] font-semibold text-ink mb-1">DAS (%)</label>
                            <input id="sim_das" type="number" name="sim_das" step="0.5" min="0" max="30" x-model.number="simDas"
                                   class="w-24 rounded-lg border-line bg-paper px-3 py-1.5 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper transition">
                            Recalcular Mix Real
                        </button>
                    </form>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-lg border border-line bg-paper p-3.5">
                        <p class="text-[11px] font-medium text-slate-600">Créditos Disponíveis</p>
                        <p class="mt-1 font-mono text-base font-bold text-ink tabular-nums" x-text="formatMoney(liveSim.credits_available)"></p>
                    </div>
                    <div class="rounded-lg border border-line bg-paper p-3.5">
                        <p class="text-[11px] font-medium text-slate-600">Faturamento Simulado</p>
                        <p class="mt-1 font-mono text-base font-bold text-ink tabular-nums" x-text="formatMoney(liveSim.simulated_sales)"></p>
                    </div>
                    <div class="rounded-lg border border-line bg-paper p-3.5">
                        <p class="text-[11px] font-medium text-slate-600">Custo Mercadoria + Frete</p>
                        <p class="mt-1 font-mono text-base font-bold text-ink tabular-nums" x-text="formatMoney(liveSim.merchandise_and_freight_cost)"></p>
                    </div>
                    <div class="rounded-lg border border-accent-200 bg-accent-50 p-3.5">
                        <p class="text-[11px] font-medium text-accent-700">DAS Estimado (<span x-text="Number(simDas).toFixed(1).replace('.', ',')"></span>%)</p>
                        <p class="mt-1 font-mono text-base font-bold text-accent-700 tabular-nums" x-text="formatMoney(liveSim.estimated_das)"></p>
                    </div>
                    <div class="rounded-lg border border-money-200 bg-money-50 p-3.5">
                        <p class="text-[11px] font-medium text-money-700">Resultado Líquido (<span x-text="Number(liveSim.estimated_margin_percent).toFixed(1).replace('.', ',')"></span>%)</p>
                        <p class="mt-1 font-mono text-base font-bold text-money-700 tabular-nums" x-text="formatMoney(liveSim.result_after_cost_and_das)"></p>
                    </div>
                </div>

                <div class="rounded-lg border border-line bg-paper/75 px-4 py-3 text-xs text-slate-600 flex items-start gap-2.5">
                    <x-icon name="circle-alert" class="size-4 text-accent-600 shrink-0 mt-0.5" />
                    <p>
                        <strong class="text-ink">Atenção contábil (Seção 9):</strong> estimativa gerencial simplificada (não representa lucro líquido contábil nem substitui a apuração pelo Simples Nacional com RBT12, substituição tributária ou momento de reconhecimento de receita).
                    </p>
                </div>
            </div>

            {{-- RBAC Users & Searchable Audit Trail --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="border-b border-line pb-3">
                        <h2 class="font-serif text-xl text-ink">Usuários &amp; Perfis RBAC</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Controle de papéis internos (`admin`, `commercial`, `purchasing`, `finance`, `logistics`, `auditor`) e clientes.</p>
                    </div>
                    <div class="mt-4 space-y-2 text-xs max-h-[440px] overflow-y-auto pr-1">
                        @foreach ($users as $listedUser)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-line bg-paper/50 px-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink truncate">{{ $listedUser->name }}</p>
                                    <p class="text-[11px] text-slate-500 truncate">{{ $listedUser->email }}</p>
                                </div>
                                @if ($isFullAdmin && (int) $listedUser->id !== (int) $operator?->id)
                                    <form method="POST" action="{{ route('admin.users.role', $listedUser) }}" class="flex items-center gap-2 shrink-0">
                                        @csrf
                                        @method('PATCH')
                                        <label for="user_role_{{ $listedUser->id }}" class="sr-only">Papel de {{ $listedUser->name }}</label>
                                        <select id="user_role_{{ $listedUser->id }}" name="role" class="rounded-lg border-line bg-surface px-2.5 py-1 text-[11px] text-ink focus:border-ink focus:ring-ink">
                                            @foreach (\App\Enums\UserRole::cases() as $roleOption)
                                                <option value="{{ $roleOption->value }}" @selected($listedUser->roleEnum()->value === $roleOption->value)>
                                                    {{ $roleOption->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-3 py-1 text-[11px] font-semibold text-paper transition">
                                            Salvar
                                        </button>
                                    </form>
                                @else
                                    <span class="rounded-md bg-surface border border-line px-2.5 py-1 text-[11px] font-medium text-slate-700 shrink-0">
                                        {{ $listedUser->roleEnum()->label() }}{{ (int) $listedUser->id === (int) $operator?->id ? ' (Sua conta)' : '' }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-3">
                        <h2 class="font-serif text-xl text-ink">Trilha de Auditoria Recente</h2>
                        <label for="audit_search_input" class="sr-only">Filtrar eventos de auditoria</label>
                        <input id="audit_search_input" type="search" x-model="auditQuery" placeholder="Filtrar ação ou usuário..."
                               class="rounded-lg border-line bg-paper px-3 py-1 text-xs text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="mt-4 space-y-2 text-xs max-h-[440px] overflow-y-auto pr-1">
                        @forelse ($auditLogs as $log)
                            @php
                                $searchHaystack = mb_strtolower($log->action . ' ' . ($log->user?->name ?? 'Sistema') . ' ' . ($log->company?->trade_name ?? ''));
                            @endphp
                            <div x-show="!auditQuery || @js($searchHaystack).includes(auditQuery.toLowerCase())"
                                 class="flex items-start justify-between gap-3 rounded-lg border border-line bg-paper/50 px-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="font-mono font-semibold text-ink truncate">{{ $log->action }}</p>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        {{ $log->user?->name ?? 'Sistema' }}
                                        @if ($log->company)
                                            · {{ $log->company->trade_name ?? $log->company->legal_name }}
                                        @endif
                                    </p>
                                </div>
                                <span class="shrink-0 font-mono text-[11px] text-slate-500 tabular-nums">{{ $log->created_at?->format('d/m H:i') }}</span>
                            </div>
                        @empty
                            <p class="text-slate-500">Nenhum evento registrado.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Reject Payment --}}
        <div x-show="rejectModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="dash-reject-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="rejectModalOpen = false" class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="dash-reject-title" class="font-serif text-xl text-ink">Rejeitar Pagamento</h3>
                        <p class="mt-0.5 text-xs text-slate-600">Empresa: <strong class="text-ink" x-text="rejectCompany"></strong></p>
                    </div>
                    <button type="button" data-compact-btn @click="rejectModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" :action="rejectAction" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="dash_reject_notes" class="block text-xs font-semibold text-ink mb-1">Motivo da rejeição</label>
                        <textarea id="dash_reject_notes" name="admin_notes" rows="3" required
                                  placeholder="Ex.: Comprovante ilegível ou valor divergente."
                                  class="w-full rounded-lg border-line bg-paper px-3 py-2 text-xs text-ink focus:border-ink focus:ring-ink"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-line">
                        <button type="button" @click="rejectModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 text-xs font-semibold text-ink">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-danger-600 hover:bg-danger-700 px-4 py-2 text-xs font-semibold text-white">Confirmar Rejeição</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Generic Delete Confirmation --}}
        <div x-show="confirmModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="dash-confirm-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="confirmModalOpen = false" class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <h3 id="dash-confirm-title" class="font-serif text-xl text-ink" x-text="confirmTitle"></h3>
                <p class="text-xs text-slate-600 leading-relaxed" x-text="confirmMessage"></p>
                <form method="POST" :action="confirmAction" class="flex justify-end gap-2 pt-3 border-t border-line">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="confirmModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 text-xs font-semibold text-ink">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-danger-600 hover:bg-danger-700 px-4 py-2 text-xs font-semibold text-white" x-text="confirmButtonText"></button>
                </form>
            </div>
        </div>

        {{-- Modal: Create Product --}}
        <div x-show="createProductModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="create-prod-title"
             class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="createProductModalOpen = false" class="w-full max-w-2xl rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 id="create-prod-title" class="font-serif text-xl text-ink">Cadastrar Novo Item B2B</h3>
                    <button type="button" data-compact-btn @click="createProductModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2 text-xs">
                    @csrf
                    <div class="sm:col-span-2">
                        <label for="cp_name" class="block font-semibold text-ink mb-1">Nome do Produto</label>
                        <input id="cp_name" type="text" name="name" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cp_sku" class="block font-semibold text-ink mb-1">SKU</label>
                        <input id="cp_sku" type="text" name="sku" placeholder="EX.: NX-SUP-021" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cp_brand" class="block font-semibold text-ink mb-1">Marca</label>
                        <input id="cp_brand" type="text" name="brand" value="NexoSupri" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cp_cat" class="block font-semibold text-ink mb-1">Categoria</label>
                        <select id="cp_cat" name="category_id" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="cp_unit" class="block font-semibold text-ink mb-1">Unidade</label>
                        <input id="cp_unit" type="text" name="unit" value="un" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cp_pres" class="block font-semibold text-ink mb-1">Apresentação</label>
                        <input id="cp_pres" type="text" name="presentation" placeholder="Caixa com 10 pacotes" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cp_cost" class="block font-semibold text-ink mb-1">Custo Compra (R$)</label>
                        <input id="cp_cost" type="number" name="cost_price" step="0.01" min="0" x-model.number="createProduct.cost_price" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cp_freight" class="block font-semibold text-ink mb-1">Frete Entrada (R$)</label>
                        <input id="cp_freight" type="number" name="inbound_freight" step="0.01" min="0" x-model.number="createProduct.inbound_freight" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cp_other" class="block font-semibold text-ink mb-1">Outros Custos (R$)</label>
                        <input id="cp_other" type="number" name="other_costs" step="0.01" min="0" x-model.number="createProduct.other_costs" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cp_markup" class="block font-semibold text-ink mb-1">Markup (%)</label>
                        <input id="cp_markup" type="number" name="markup_percent" step="0.1" min="0" x-model.number="createProduct.markup_percent" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cp_das" class="block font-semibold text-ink mb-1">DAS (%)</label>
                        <input id="cp_das" type="number" name="das_rate" step="0.1" min="0" x-model.number="createProduct.das_rate" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <span class="block font-semibold text-ink mb-1">Estoque Inicial / Mínimo</span>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" name="stock" min="0" value="20" required aria-label="Estoque inicial" class="rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                            <input type="number" name="min_stock" min="0" value="5" required aria-label="Estoque mínimo" class="rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                    </div>
                    <div class="sm:col-span-2 rounded-lg border border-money-200 bg-money-50 px-3.5 py-2 text-money-700 flex items-center justify-between">
                        <span>Preço sugerido pela fórmula NexoSupri:</span>
                        <strong class="font-mono text-sm tabular-nums" x-text="formatMoney(simulatedSalePrice(createProduct))"></strong>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cp_gallery" class="block font-semibold text-ink mb-1">Fotos do Carrossel (opcional, até 8 imagens)</label>
                        <input id="cp_gallery" type="file" name="gallery_files[]" multiple accept="image/*" class="w-full rounded-lg border-line bg-paper px-3 py-1.5 text-ink">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cp_desc" class="block font-semibold text-ink mb-1">Descrição Completa</label>
                        <textarea id="cp_desc" name="description" rows="2" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink"></textarea>
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-line">
                        <button type="button" @click="createProductModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 font-semibold text-ink">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper">Salvar Produto</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Edit Product --}}
        <div x-show="editProductModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="edit-prod-title"
             class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="editProductModalOpen = false" class="w-full max-w-2xl rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 id="edit-prod-title" class="font-serif text-xl text-ink">Editar Produto &amp; Precificação</h3>
                    <button type="button" data-compact-btn @click="editProductModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" :action="editProductAction" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2 text-xs">
                    @csrf
                    @method('PATCH')
                    <div class="sm:col-span-2">
                        <label for="ep_name" class="block font-semibold text-ink mb-1">Nome do Produto</label>
                        <input id="ep_name" type="text" name="name" x-model="editProduct.name" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="ep_sku" class="block font-semibold text-ink mb-1">SKU</label>
                        <input id="ep_sku" type="text" name="sku" x-model="editProduct.sku" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="ep_brand" class="block font-semibold text-ink mb-1">Marca</label>
                        <input id="ep_brand" type="text" name="brand" x-model="editProduct.brand" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="ep_cat" class="block font-semibold text-ink mb-1">Categoria</label>
                        <select id="ep_cat" name="category_id" x-model="editProduct.category_id" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="ep_unit" class="block font-semibold text-ink mb-1">Unidade</label>
                        <input id="ep_unit" type="text" name="unit" x-model="editProduct.unit" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="ep_pres" class="block font-semibold text-ink mb-1">Apresentação</label>
                        <input id="ep_pres" type="text" name="presentation" x-model="editProduct.presentation" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="ep_cost" class="block font-semibold text-ink mb-1">Custo Compra (R$)</label>
                        <input id="ep_cost" type="number" name="cost_price" x-model="editProduct.cost_price" step="0.01" min="0" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="ep_freight" class="block font-semibold text-ink mb-1">Frete Entrada (R$)</label>
                        <input id="ep_freight" type="number" name="inbound_freight" x-model="editProduct.inbound_freight" step="0.01" min="0" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="ep_other" class="block font-semibold text-ink mb-1">Outros Custos (R$)</label>
                        <input id="ep_other" type="number" name="other_costs" x-model="editProduct.other_costs" step="0.01" min="0" class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="ep_markup" class="block font-semibold text-ink mb-1">Markup (%)</label>
                        <input id="ep_markup" type="number" name="markup_percent" x-model="editProduct.markup_percent" step="0.1" min="0" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="ep_das" class="block font-semibold text-ink mb-1">DAS (%)</label>
                        <input id="ep_das" type="number" name="das_rate" x-model="editProduct.das_rate" step="0.1" min="0" required class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <span class="block font-semibold text-ink mb-1">Estoque / Mínimo</span>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" name="stock" x-model="editProduct.stock" min="0" required aria-label="Estoque atual" class="rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                            <input type="number" name="min_stock" x-model="editProduct.min_stock" min="0" required aria-label="Estoque mínimo" class="rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                        </div>
                    </div>
                    <div class="sm:col-span-2 rounded-lg border border-money-200 bg-money-50 px-3.5 py-2 text-money-700 flex items-center justify-between">
                        <span>Preço calculado na fórmula NexoSupri:</span>
                        <strong class="font-mono text-sm tabular-nums" x-text="formatMoney(simulatedSalePrice(editProduct))"></strong>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="ep_reason" class="block font-semibold text-ink mb-1">Motivo da alteração de preço/custo (auditoria)</label>
                        <input id="ep_reason" type="text" name="price_reason" placeholder="Ex.: Reajuste de tabela do fornecedor" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="ep_gallery" class="block font-semibold text-ink mb-1">Adicionar Fotos à Galeria (opcional)</label>
                        <input id="ep_gallery" type="file" name="gallery_files[]" multiple accept="image/*" class="w-full rounded-lg border-line bg-paper px-3 py-1.5 text-ink">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="ep_desc" class="block font-semibold text-ink mb-1">Descrição Completa</label>
                        <textarea id="ep_desc" name="description" x-model="editProduct.description" rows="2" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink"></textarea>
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-line">
                        <button type="button" @click="editProductModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 font-semibold text-ink">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper">Atualizar Produto</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Create Category --}}
        <div x-show="createCategoryModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="create-cat-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="createCategoryModalOpen = false" class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 id="create-cat-title" class="font-serif text-xl text-ink">Nova Categoria</h3>
                    <button type="button" data-compact-btn @click="createCategoryModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label for="cat_name" class="block font-semibold text-ink mb-1">Nome da categoria</label>
                        <input id="cat_name" type="text" name="name" required class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="cat_parent" class="block font-semibold text-ink mb-1">Categoria pai (opcional)</label>
                        <select id="cat_parent" name="parent_id" class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                            <option value="">Nenhuma (Categoria principal)</option>
                            @foreach ($categories->whereNull('parent_id') as $parentCat)
                                <option value="{{ $parentCat->id }}">{{ $parentCat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-line">
                        <button type="button" @click="createCategoryModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 font-semibold text-ink">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper">Criar Categoria</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
