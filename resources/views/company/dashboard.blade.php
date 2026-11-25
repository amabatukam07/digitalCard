<x-app-layout :metaTitle="'Painel da Empresa — ' . $company->trade_name" :metaDescription="'Indicadores de consumo, saldo de créditos, aprovações e equipe da empresa na NexoSupri.'">
    <div class="app-shell py-8 space-y-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-line pb-5">
            <div>
                <x-eyebrow>{{ $company->legal_name }} · CNPJ {{ $company->cnpj }}</x-eyebrow>
                <h1 class="mt-1 font-serif text-3xl text-ink">Painel da Empresa — {{ $company->trade_name }}</h1>
                <p class="text-xs sm:text-sm text-slate-600 mt-1">
                    @if($activeContract)
                        Plano ativo: <strong class="text-ink">{{ $activeContract->plan?->name }}</strong> ·
                    @endif
                    Seu papel: <strong class="text-ink">{{ $isManager ? 'Gestor da empresa' : 'Solicitante' }}</strong>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('wallet.show') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-line bg-surface hover:border-ink px-4 py-2.5 text-xs font-semibold text-ink transition">
                    <x-icon name="wallet" class="size-4 text-money-600" />
                    <span>Carteira de Créditos</span>
                </a>
                <a href="{{ route('catalog.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-ink hover:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-paper transition">
                    <span>Fazer pedido</span>
                    <x-icon name="arrow-right" class="size-4" />
                </a>
            </div>
        </div>

        @if($isLowBalance)
            <div role="alert" class="rounded-xl border border-accent-200 bg-accent-50 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="flex items-start gap-2.5">
                    <x-icon name="alert-triangle" class="size-5 text-accent-700 shrink-0" />
                    <div>
                        <p class="font-semibold text-accent-950">Alerta de saldo baixo de créditos</p>
                        <p class="text-accent-800 mt-0.5">
                            O saldo atual ({{ number_format($availableCredits, 2, ',', '.') }} créditos) está igual ou abaixo do limite de alerta ({{ number_format((float) $company->low_balance_threshold, 2, ',', '.') }} créditos).
                        </p>
                    </div>
                </div>
                <a href="{{ route('wallet.show') }}" class="rounded-lg bg-ink text-paper font-semibold px-4 py-2 shrink-0">
                    Contratar nova carga →
                </a>
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="col-span-2 lg:col-span-1 bg-surface rounded-xl border border-line p-5">
                <span class="text-xs text-slate-500">Saldo disponível</span>
                <p class="font-mono text-2xl font-bold text-money-700 tabular-nums mt-1">{{ number_format($availableCredits, 2, ',', '.') }} cr.</p>
                <span class="text-[11px] text-slate-500">R$ {{ number_format($availableCredits, 2, ',', '.') }} em poder de compra</span>
            </div>

            <div class="bg-surface rounded-xl border border-line p-5">
                <span class="text-xs text-slate-500">Créditos contratados</span>
                <p class="font-mono text-2xl font-bold text-ink tabular-nums mt-1">{{ number_format($totalAcquired, 2, ',', '.') }} cr.</p>
                <span class="text-[11px] text-slate-500">Total acumulado no contrato</span>
            </div>

            <div class="bg-surface rounded-xl border border-line p-5">
                <span class="text-xs text-slate-500">Créditos utilizados</span>
                <p class="font-mono text-2xl font-bold text-ink tabular-nums mt-1">{{ number_format($totalSpent, 2, ',', '.') }} cr.</p>
                <span class="text-[11px] text-slate-500">{{ $budgetUsedPercent }}% do orçamento consumido</span>
            </div>

            <div class="bg-surface rounded-xl border border-line p-5">
                <span class="text-xs text-slate-500">Consumido no mês</span>
                <p class="font-mono text-2xl font-bold text-ink tabular-nums mt-1">R$ {{ number_format($spentThisMonth, 2, ',', '.') }}</p>
                <span class="text-[11px] text-slate-500">Pedidos válidos do mês atual</span>
            </div>

            <div class="bg-surface rounded-xl border border-line p-5">
                <span class="text-xs text-slate-500">Pedidos em andamento</span>
                <p class="font-mono text-2xl font-bold text-ink tabular-nums mt-1">{{ $inProgressOrdersCount }}</p>
                <span class="text-[11px] text-slate-500">{{ $awaitingApprovalOrders->count() }} aguardando aprovação</span>
            </div>
        </div>

        @if($awaitingApprovalOrders->isNotEmpty())
            <section class="bg-surface rounded-xl border border-accent-200 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <x-eyebrow>Governança de compras</x-eyebrow>
                        <h2 class="font-serif text-xl text-ink mt-0.5">Pedidos aguardando aprovação do gestor</h2>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-accent-50 border border-accent-200 text-xs font-semibold text-accent-900 tabular-nums">
                        {{ $awaitingApprovalOrders->count() }} pendente(s)
                    </span>
                </div>

                <div class="divide-y divide-line border border-line rounded-lg overflow-hidden">
                    @foreach($awaitingApprovalOrders as $pendingOrd)
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs bg-paper/50">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="{{ route('orders.show', $pendingOrd) }}" class="font-mono font-semibold text-ink underline">
                                        #PED-{{ str_pad((string) $pendingOrd->id, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                    <span class="text-slate-600">Solicitante: <strong>{{ $pendingOrd->user?->name }}</strong></span>
                                    @if($pendingOrd->department)
                                        <span class="px-2 py-0.5 rounded bg-surface border border-line text-[11px]">{{ $pendingOrd->department }}</span>
                                    @endif
                                </div>
                                <p class="text-slate-600">
                                    {{ $pendingOrd->items->count() }} item(ns) · Valor: <strong class="font-mono text-ink">{{ number_format((float) $pendingOrd->total_amount, 2, ',', '.') }} créditos</strong>
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('orders.show', $pendingOrd) }}" class="px-3 py-1.5 rounded-md border border-line bg-surface font-medium text-ink hover:border-ink">
                                    Revisar
                                </a>
                                @if($isManager)
                                    <form action="{{ route('orders.approve', $pendingOrd) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-3.5 py-1.5 rounded-md bg-money-600 hover:bg-money-700 text-paper font-semibold transition">
                                            Aprovar e debitar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <section class="bg-surface rounded-xl border border-line p-6 space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-ink">Consumo mensal (últimos 6 meses)</h2>
                    <p class="text-xs text-slate-500">Evolução mensal do abastecimento em créditos / R$.</p>
                </div>

                <div class="grid grid-cols-6 gap-3 items-end h-40 pt-4 border-b border-line pb-2">
                    @foreach($monthlyConsumption as $mRow)
                        <div class="flex flex-col items-center gap-1.5 h-full justify-end">
                            <span class="text-[10px] font-mono text-slate-600 tabular-nums">
                                {{ $mRow['total'] > 0 ? number_format($mRow['total'], 0, ',', '.') : '0' }}
                            </span>
                            <div class="w-full max-w-[36px] bg-ink rounded-t transition-all" style="height: {{ max(4, $mRow['percent']) }}%"></div>
                            <span class="text-[11px] text-slate-600 font-medium">{{ $mRow['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="space-y-1.5 text-xs pt-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-600 font-medium">Evolução do orçamento utilizado</span>
                        <span class="font-mono font-semibold text-ink tabular-nums">{{ $budgetUsedPercent }}%</span>
                    </div>
                    <div class="w-full h-2 bg-paper-dim rounded-full overflow-hidden border border-line">
                        <div class="h-full bg-money-600 rounded-full" style="width: {{ $budgetUsedPercent }}%"></div>
                    </div>
                </div>
            </section>

            <section class="bg-surface rounded-xl border border-line p-6 space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-ink">Consumo por categoria &amp; Top insumos</h2>
                    <p class="text-xs text-slate-500">Distribuição do saldo consumido por tipo de material.</p>
                </div>

                @if($categoryConsumption->isNotEmpty())
                    <div class="space-y-2.5 text-xs">
                        @foreach($categoryConsumption as $catRow)
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-ink">{{ $catRow['name'] }} ({{ $catRow['units'] }} un.)</span>
                                    <span class="font-mono tabular-nums font-semibold text-ink">R$ {{ number_format($catRow['total'], 2, ',', '.') }}</span>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-paper-dim overflow-hidden">
                                    <div class="h-full bg-ink rounded-full" style="width: {{ $catRow['percent'] }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-500">Nenhum pedido registrado ainda nesta empresa.</p>
                @endif

                @if($topProducts->isNotEmpty())
                    <div class="pt-4 border-t border-line space-y-2 text-xs">
                        <h3 class="font-semibold text-slate-700">Insumos mais comprados pela empresa</h3>
                        <ul class="divide-y divide-line rounded-lg border border-line bg-paper overflow-hidden">
                            @foreach($topProducts as $tp)
                                <li class="px-3.5 py-2 flex items-center justify-between gap-3">
                                    <span class="font-medium text-ink truncate">{{ $tp->product_name }}</span>
                                    <span class="font-mono tabular-nums text-slate-600 shrink-0">{{ (int) $tp->total_units }} un.</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        @if($isManager)
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                <section class="xl:col-span-7 bg-surface rounded-xl border border-line p-6 space-y-5">
                    <div>
                        <x-eyebrow>Multiusuário</x-eyebrow>
                        <h2 class="font-serif text-2xl text-ink mt-0.5">Equipe e alçadas de compra</h2>
                        <p class="text-xs text-slate-600">Gerencie gestores e solicitantes da empresa {{ $company->trade_name }}.</p>
                    </div>

                    <div class="divide-y divide-line border border-line rounded-lg overflow-hidden text-xs">
                        @foreach($members as $member)
                            <div class="p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-paper/40">
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink truncate">{{ $member->user?->name }}</p>
                                    <p class="text-slate-500 truncate">{{ $member->user?->email }} · {{ $member->department ?: 'Geral' }}</p>
                                </div>
                                <form action="{{ route('company.members.update', $member) }}" method="POST" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="company_role" class="rounded border-line text-xs py-1 px-2">
                                        <option value="manager" {{ $member->company_role === 'manager' ? 'selected' : '' }}>Gestor</option>
                                        <option value="requester" {{ $member->company_role === 'requester' ? 'selected' : '' }}>Solicitante</option>
                                    </select>
                                    <input type="number" name="spending_limit" step="0.01" min="0"
                                           value="{{ $member->spending_limit }}" placeholder="Limite (R$)"
                                           class="w-28 rounded border-line font-mono text-xs py-1 px-2">
                                    <button type="submit" class="rounded bg-ink text-paper px-3 py-1.5 font-medium">Salvar</button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    <form action="{{ route('company.members.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-line text-xs">
                        @csrf
                        <h3 class="sm:col-span-2 font-semibold text-ink">Adicionar novo colaborador</h3>
                        <input type="text" name="name" required placeholder="Nome completo" class="rounded-md border-line py-2 px-3">
                        <input type="email" name="email" required placeholder="E-mail corporativo" class="rounded-md border-line py-2 px-3">
                        <input type="password" name="password" required placeholder="Senha inicial (mín. 8)" class="rounded-md border-line py-2 px-3">
                        <select name="company_role" class="rounded-md border-line py-2 px-3">
                            <option value="requester">Solicitante (requer aprovação)</option>
                            <option value="manager">Gestor da empresa</option>
                        </select>
                        <input type="number" name="spending_limit" step="0.01" min="0" placeholder="Limite por pedido (opcional)" class="rounded-md border-line font-mono py-2 px-3">
                        <input type="text" name="department" placeholder="Setor (ex: Copa, Administrativo)" class="rounded-md border-line py-2 px-3">
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 text-paper font-semibold px-4 py-2.5 transition">
                                Cadastrar colaborador
                            </button>
                        </div>
                    </form>
                </section>

                <section class="xl:col-span-5 bg-surface rounded-xl border border-line p-6 space-y-4">
                    <div>
                        <x-eyebrow>Política interna</x-eyebrow>
                        <h2 class="font-serif text-2xl text-ink mt-0.5">Regras de aprovação e alertas</h2>
                    </div>

                    <form action="{{ route('company.settings.update') }}" method="POST" class="space-y-4 text-xs">
                        @csrf
                        @method('PATCH')

                        <label class="flex items-start gap-2.5 p-3 rounded-lg border border-line bg-paper cursor-pointer">
                            <input type="checkbox" name="require_order_approval" value="1" {{ $company->require_order_approval ? 'checked' : '' }} class="mt-0.5 rounded border-line">
                            <span>
                                <strong class="block text-ink">Exigir aprovação do Gestor para pedidos de Solicitantes</strong>
                                <span class="text-slate-600">Pedidos criados por solicitantes ficam em "Aguardando aprovação" até confirmação do gestor.</span>
                            </span>
                        </label>

                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Alerta de saldo baixo (créditos)</label>
                            <input type="number" name="low_balance_threshold" step="0.01" min="0" required
                                   value="{{ (float) $company->low_balance_threshold }}"
                                   class="w-full rounded-md border-line font-mono py-2 px-3">
                        </div>

                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Endereço de faturamento</label>
                            <input type="text" name="billing_address" value="{{ $company->billing_address }}" class="w-full rounded-md border-line py-2 px-3">
                        </div>

                        <div>
                            <label class="block font-medium text-slate-700 mb-1">CEP de faturamento</label>
                            <input type="text" name="billing_cep" value="{{ $company->billing_cep }}" class="w-full rounded-md border-line font-mono py-2 px-3">
                        </div>

                        <button type="submit" class="w-full rounded-lg bg-ink hover:bg-slate-800 text-paper font-semibold py-2.5 transition">
                            Salvar configurações da empresa
                        </button>
                    </form>
                </section>
            </div>
        @endif
    </div>
</x-app-layout>
