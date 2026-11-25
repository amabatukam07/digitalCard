@php
    $currency = static fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $canManageCompanies = (bool) auth()->user()?->canManageCompanies();
@endphp

<x-admin-layout title="Empresas Clientes">
    <div x-data="{
            createModalOpen: false,
            statusModalOpen: false,
            statusAction: '',
            statusTarget: 'suspended',
            statusCompanyName: '',
            openStatusModal(action, companyName, targetStatus) {
                this.statusAction = action;
                this.statusCompanyName = companyName;
                this.statusTarget = targetStatus;
                this.statusModalOpen = true;
            }
         }"
         @keydown.escape.window="createModalOpen = false; statusModalOpen = false"
         class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end justify-between border-b border-line pb-5">
            <div>
                <x-eyebrow>Clientes Corporativos (B2B)</x-eyebrow>
                <h1 class="mt-1 font-serif text-2xl sm:text-3xl text-ink">Empresas Clientes, Contratos &amp; Carteiras</h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600 max-w-2xl">
                    Gerencie cadastros de CNPJ, políticas de aprovação por gestor, contratos vigentes e alertas de saldo baixo.
                </p>
            </div>
            @if ($canManageCompanies)
                <button type="button" @click="createModalOpen = true"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-paper transition shrink-0">
                    <x-icon name="plus" class="size-4" />
                    <span>Cadastrar Empresa</span>
                </button>
            @endif
        </div>

        {{-- KPIs --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Empresas Ativas</p>
                <p class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums">{{ $companyStats['active'] }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">de {{ $companyStats['total'] }} cadastradas</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Suspensas</p>
                <p class="mt-1 font-mono text-2xl font-bold text-danger-700 tabular-nums">{{ $companyStats['suspended'] }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Bloqueadas para novas compras</p>
            </div>
            <div class="rounded-xl border border-accent-200 bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-accent-700">Alerta de Saldo Baixo</p>
                <p class="mt-1 font-mono text-2xl font-bold text-accent-700 tabular-nums">{{ $companyStats['lowBalance'] }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Abaixo do teto de reposição</p>
            </div>
            <div class="rounded-xl border border-money-200 bg-surface p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-money-700">Saldo Total em Carteiras</p>
                <p class="mt-1 font-mono text-2xl font-bold text-money-700 tabular-nums">{{ $currency($companyStats['openCredits']) }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Obrigações em créditos (1:1)</p>
            </div>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-surface rounded-xl border border-line p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5" role="navigation" aria-label="Filtrar empresas">
                @php
                    $companyTabs = [
                        '' => 'Todas (' . $companyStats['total'] . ')',
                        'active' => 'Ativas (' . $companyStats['active'] . ')',
                        'suspended' => 'Suspensas (' . $companyStats['suspended'] . ')',
                        'low_balance' => 'Saldo Baixo (' . $companyStats['lowBalance'] . ')',
                    ];
                @endphp
                @foreach ($companyTabs as $tabKey => $tabLabel)
                    <a href="{{ route('admin.companies.index', array_filter(['status' => $tabKey !== '' ? $tabKey : null, 'q' => $search ?: null])) }}"
                       @if($statusFilter === $tabKey) aria-current="page" @endif
                       class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $statusFilter === $tabKey ? 'bg-ink text-paper' : 'border border-line bg-paper text-slate-700 hover:border-ink' }}">
                        {{ $tabLabel }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.companies.index') }}" class="flex items-center gap-2 w-full lg:w-auto">
                @if($statusFilter !== '')
                    <input type="hidden" name="status" value="{{ $statusFilter }}">
                @endif
                <label for="companies-search" class="sr-only">Buscar por razão social, nome fantasia, CNPJ ou e-mail</label>
                <div class="relative flex-1 lg:w-72">
                    <x-icon name="search" class="size-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input id="companies-search" type="search" name="q" value="{{ $search }}"
                           placeholder="Razão social, fantasia ou CNPJ..."
                           class="w-full rounded-lg border-line bg-paper pl-8 pr-3 py-2 text-xs text-ink focus:border-ink focus:ring-ink">
                </div>
                <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-paper transition">
                    Buscar
                </button>
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
            <div class="table-scroll-region">
                <table class="min-w-full divide-y divide-line text-xs">
                    <thead class="bg-paper text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Empresa / CNPJ</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Plano &amp; Governança</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Saldo Créditos</th>
                            <th scope="col" class="px-4 py-3 text-center font-semibold">Usuários / Pedidos</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($companies as $company)
                            @php
                                $activePlanName = $company->contracts
                                    ->firstWhere('status', \App\Models\Contract::STATUS_ACTIVE)
                                    ?->plan?->name ?? 'Sem plano ativo';
                                $balance = (float) ($company->wallet?->balance ?? 0);
                                $isLowBalance = $balance <= (float) $company->low_balance_threshold;
                                $displayCompanyName = $company->trade_name ?: $company->legal_name;
                            @endphp
                            <tr class="hover:bg-paper/45 transition-colors">
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-ink">{{ $displayCompanyName }}</p>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        <span class="font-mono tabular-nums">{{ $company->cnpj }}</span> · {{ $company->email }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-medium text-ink">{{ $activePlanName }}</p>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        {{ $company->require_order_approval ? 'Aprovação de gestor obrigatória' : 'Compra direta liberada' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <span class="font-mono font-bold tabular-nums {{ $isLowBalance ? 'text-accent-700' : 'text-money-700' }}">
                                        {{ $currency($balance) }}
                                    </span>
                                    @if ($isLowBalance)
                                        <span class="block text-[10px] font-semibold text-accent-700 mt-0.5">
                                            Abaixo de {{ $currency($company->low_balance_threshold) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center font-mono text-slate-700 tabular-nums">
                                    {{ $company->memberships_count }} usuários · {{ $company->orders_count }} pedidos
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex rounded-md border px-2.5 py-0.5 text-[11px] font-semibold {{ $company->status === 'active' ? 'bg-money-50 border-money-200 text-money-700' : 'bg-danger-50 border-danger-200 text-danger-700' }}">
                                        {{ $company->status === 'active' ? 'Ativa' : 'Suspensa' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    @if ($canManageCompanies)
                                        @if ($company->status === 'active')
                                            <button type="button"
                                                    @click="openStatusModal('{{ route('admin.companies.status', $company) }}', '{{ addslashes($displayCompanyName) }}', 'suspended')"
                                                    class="rounded-lg border border-danger-200 bg-danger-50 hover:bg-danger-100 px-3 py-1.5 font-semibold text-danger-700 transition">
                                                Suspender
                                            </button>
                                        @else
                                            <button type="button"
                                                    @click="openStatusModal('{{ route('admin.companies.status', $company) }}', '{{ addslashes($displayCompanyName) }}', 'active')"
                                                    class="rounded-lg border border-money-200 bg-money-50 hover:bg-money-100 px-3 py-1.5 font-semibold text-money-700 transition">
                                                Reativar
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-[11px] text-slate-500">Somente leitura</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">Nenhuma empresa encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $companies->links() }}</div>

        {{-- Confirm Company Status Modal --}}
        <div x-show="statusModalOpen" x-cloak
             role="dialog" aria-modal="true" aria-labelledby="company-status-title"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="statusModalOpen = false" class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <h2 id="company-status-title" class="font-serif text-xl text-ink"
                    x-text="statusTarget === 'suspended' ? 'Suspender Empresa Cliente' : 'Reativar Empresa Cliente'"></h2>
                <p class="text-xs text-slate-600 leading-relaxed">
                    <span x-show="statusTarget === 'suspended'">
                        Ao suspender <strong class="text-ink" x-text="statusCompanyName"></strong>, seus usuários ficam impedidos de fechar novos pedidos até a reativação.
                    </span>
                    <span x-show="statusTarget === 'active'">
                        Confirma a reativação cadastral de <strong class="text-ink" x-text="statusCompanyName"></strong> para retomar compras no catálogo?
                    </span>
                </p>
                <form method="POST" :action="statusAction" class="flex justify-end gap-2 pt-3 border-t border-line">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" :value="statusTarget">
                    <button type="button" @click="statusModalOpen = false" class="rounded-lg border border-line bg-paper px-4 py-2 text-xs font-semibold text-ink">
                        Cancelar
                    </button>
                    <button type="submit"
                            :class="statusTarget === 'suspended' ? 'bg-danger-600 hover:bg-danger-700 text-white' : 'bg-money-600 hover:bg-money-700 text-white'"
                            class="rounded-lg px-4 py-2 text-xs font-semibold transition">
                        Confirmar Alteração
                    </button>
                </form>
            </div>
        </div>

        {{-- Create Company Modal --}}
        <div x-show="createModalOpen" x-cloak
             role="dialog" aria-modal="true" aria-labelledby="create-company-title"
             class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ink/65 backdrop-blur-sm p-4">
            <div @click.away="createModalOpen = false" class="w-full max-w-lg rounded-2xl border border-line bg-surface p-6 shadow-float space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h2 id="create-company-title" class="font-serif text-xl text-ink">Cadastrar Nova Empresa Cliente</h2>
                    <button type="button" data-compact-btn @click="createModalOpen = false" aria-label="Fechar modal" class="text-slate-500 hover:text-ink p-1">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.companies.store') }}" class="grid gap-3.5 sm:grid-cols-2 text-xs">
                    @csrf
                    <div>
                        <label for="co_cnpj" class="block font-semibold text-ink mb-1">CNPJ</label>
                        <input id="co_cnpj" type="text" name="cnpj" required placeholder="00.000.000/0001-00"
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="co_ie" class="block font-semibold text-ink mb-1">Inscrição Estadual</label>
                        <input id="co_ie" type="text" name="state_registration" placeholder="Isento ou número"
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="co_legal" class="block font-semibold text-ink mb-1">Razão Social</label>
                        <input id="co_legal" type="text" name="legal_name" required
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="co_trade" class="block font-semibold text-ink mb-1">Nome Fantasia</label>
                        <input id="co_trade" type="text" name="trade_name" required
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="co_email" class="block font-semibold text-ink mb-1">E-mail Corporativo</label>
                        <input id="co_email" type="email" name="email" required
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="co_phone" class="block font-semibold text-ink mb-1">Telefone</label>
                        <input id="co_phone" type="text" name="phone"
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div>
                        <label for="co_low_balance" class="block font-semibold text-ink mb-1">Alerta de Saldo Baixo (R$)</label>
                        <input id="co_low_balance" type="number" name="low_balance_threshold" step="0.01" min="0" value="200"
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 font-mono text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="co_address" class="block font-semibold text-ink mb-1">Endereço de Faturamento</label>
                        <input id="co_address" type="text" name="billing_address"
                               class="w-full rounded-lg border-line bg-paper px-3 py-2 text-ink focus:border-ink focus:ring-ink">
                    </div>
                    <div class="sm:col-span-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-line">
                        <label for="co_require_approval" class="inline-flex items-center gap-2 text-slate-700 cursor-pointer">
                            <input id="co_require_approval" type="checkbox" name="require_order_approval" value="1" checked
                                   class="rounded border-line text-ink focus:ring-ink">
                            <span>Exigir aprovação do gestor nos pedidos</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-ink hover:bg-slate-800 px-4 py-2 font-semibold text-paper transition shrink-0">
                            Salvar Empresa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
