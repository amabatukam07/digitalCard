@props([
    'title' => 'Painel Administrativo',
    'subtitle' => null,
])
@php
    $adminUser = auth()->user();
    $roleLabel = $adminUser?->roleEnum()->label() ?? 'Backoffice';
    $userInitials = strtoupper(mb_substr(trim((string) ($adminUser?->name ?? 'NS')), 0, 2));
    $pendingPaymentsAlert = (int) ($adminPendingPaymentsCount ?? 0);
    $pendingOrdersAlert = (int) ($adminPendingOrdersCount ?? 0);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-paper">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">
        <script>
            try {
                document.querySelector('meta[name="color-scheme"]').content = localStorage.getItem("color-scheme") ?? "light dark";
            } catch {}
        </script>

        <title>{{ $title }} — Backoffice NexoSupri</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            [x-cloak] { display: none !important; }
            .table-scroll-region {
                overflow: auto;
                max-inline-size: 100%;
            }
            @media (prefers-reduced-motion: reduce) {
                *, ::before, ::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                    scroll-behavior: auto !important;
                }
            }
        </style>
    </head>
    <body class="min-h-full font-sans antialiased text-ink bg-paper overflow-x-clip selection:bg-accent-400 selection:text-slate-950"
          x-data="{ sidebarOpen: false }"
          @keydown.escape.window="sidebarOpen = false">

        <a href="#admin-main-content"
           class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-lg focus:bg-ink focus:px-4 focus:py-2.5 focus:text-xs focus:font-semibold focus:text-paper focus:shadow-float">
            Pular para o conteúdo principal
        </a>

        <div class="min-h-screen flex">
            {{-- Mobile Backdrop --}}
            <div x-show="sidebarOpen"
                 x-cloak
                 @click="sidebarOpen = false"
                 aria-hidden="true"
                 class="fixed inset-0 z-40 bg-ink/60 backdrop-blur-sm md:hidden"></div>

            {{-- Sidebar Dark Editorial --}}
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
                   aria-label="Navegação administrativa"
                   class="header-dark-bar fixed inset-y-0 left-0 z-50 w-72 bg-ink text-paper border-r border-slate-800 flex flex-col justify-between transition-transform duration-200 md:sticky md:top-0 md:h-screen shrink-0">

                <div class="flex flex-col min-h-0 flex-1">
                    {{-- Brand --}}
                    <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between gap-2">
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 min-w-0">
                            <div class="h-9 w-9 rounded-md bg-paper text-ink flex items-center justify-center font-serif text-lg font-bold shrink-0">
                                NS
                            </div>
                            <div class="min-w-0">
                                <span class="font-serif text-paper text-xl leading-none block">NexoSupri</span>
                                <span class="text-[11px] text-slate-300 block mt-0.5 truncate">Backoffice NexoSupri</span>
                            </div>
                        </a>
                        <button @click="sidebarOpen = false" type="button" data-compact-btn aria-label="Fechar menu lateral"
                                class="md:hidden h-8 w-8 rounded-md flex items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-paper">
                            <x-icon name="x" class="size-4" />
                        </button>
                    </div>

                    {{-- Logged Operator & RBAC Role --}}
                    @if($adminUser)
                        <div class="px-5 py-3.5 border-b border-slate-800 bg-slate-900/60 flex items-center gap-3">
                            <span class="h-9 w-9 rounded-md bg-slate-800 border border-slate-700 text-paper font-semibold text-xs flex items-center justify-center shrink-0">
                                {{ $userInitials }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-paper truncate">{{ $adminUser->name }}</p>
                                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-accent-300">
                                    <x-icon name="shield-check" class="size-3 shrink-0" />
                                    <span class="truncate">{{ $roleLabel }}</span>
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Grouped Navigation (Gestalt) --}}
                    <nav class="flex-1 overflow-y-auto px-3.5 py-4 space-y-5 text-xs font-medium">
                        <div class="space-y-1">
                            <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Operação Diária</p>

                            <a href="{{ route('admin.dashboard') }}"
                               @if(request()->routeIs('admin.dashboard') && ! in_array(request('tab'), ['catalogo', 'governanca'], true)) aria-current="page" @endif
                               class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('admin.dashboard') && ! in_array(request('tab'), ['catalogo', 'governanca'], true) ? 'bg-slate-800 text-accent-300 font-semibold border border-slate-700' : 'text-slate-300 hover:bg-slate-800/80 hover:text-paper' }}">
                                <span class="flex items-center gap-2.5 min-w-0">
                                    <x-icon name="trending-up" class="size-4 shrink-0" />
                                    <span class="truncate">Visão Geral &amp; KPIs</span>
                                </span>
                            </a>

                            <a href="{{ route('admin.payments.index') }}"
                               @if(request()->routeIs('admin.payments.*')) aria-current="page" @endif
                               class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('admin.payments.*') ? 'bg-slate-800 text-accent-300 font-semibold border border-slate-700' : 'text-slate-300 hover:bg-slate-800/80 hover:text-paper' }}">
                                <span class="flex items-center gap-2.5 min-w-0">
                                    <x-icon name="qr-code" class="size-4 shrink-0" />
                                    <span class="truncate">Pagamentos &amp; Créditos</span>
                                </span>
                                @if($pendingPaymentsAlert > 0)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono tabular-nums bg-danger-600 text-white font-semibold">{{ $pendingPaymentsAlert }}</span>
                                @endif
                            </a>

                            <a href="{{ route('admin.orders.index') }}"
                               @if(request()->routeIs('admin.orders.*')) aria-current="page" @endif
                               class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('admin.orders.*') ? 'bg-slate-800 text-accent-300 font-semibold border border-slate-700' : 'text-slate-300 hover:bg-slate-800/80 hover:text-paper' }}">
                                <span class="flex items-center gap-2.5 min-w-0">
                                    <x-icon name="truck" class="size-4 shrink-0" />
                                    <span class="truncate">Pedidos &amp; Entregas</span>
                                </span>
                                @if($pendingOrdersAlert > 0)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono tabular-nums bg-accent-600 text-paper font-semibold">{{ $pendingOrdersAlert }}</span>
                                @endif
                            </a>
                        </div>

                        <div class="space-y-1 pt-3 border-t border-slate-800">
                            <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Gestão B2B &amp; Suprimentos</p>

                            <a href="{{ route('admin.companies.index') }}"
                               @if(request()->routeIs('admin.companies.*')) aria-current="page" @endif
                               class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('admin.companies.*') ? 'bg-slate-800 text-accent-300 font-semibold border border-slate-700' : 'text-slate-300 hover:bg-slate-800/80 hover:text-paper' }}">
                                <x-icon name="layers" class="size-4 shrink-0" />
                                <span class="truncate">Empresas Clientes</span>
                            </a>

                            <a href="{{ route('admin.plans.index') }}"
                               @if(request()->routeIs('admin.plans.*')) aria-current="page" @endif
                               class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('admin.plans.*') ? 'bg-slate-800 text-accent-300 font-semibold border border-slate-700' : 'text-slate-300 hover:bg-slate-800/80 hover:text-paper' }}">
                                <x-icon name="credit-card" class="size-4 shrink-0" />
                                <span class="truncate">Planos &amp; Contratos</span>
                            </a>

                            <a href="{{ route('admin.suppliers.index') }}"
                               @if(request()->routeIs('admin.suppliers.*')) aria-current="page" @endif
                               class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('admin.suppliers.*') ? 'bg-slate-800 text-accent-300 font-semibold border border-slate-700' : 'text-slate-300 hover:bg-slate-800/80 hover:text-paper' }}">
                                <x-icon name="package" class="size-4 shrink-0" />
                                <span class="truncate">Fornecedores &amp; Estoque</span>
                            </a>

                            <a href="{{ route('admin.dashboard', ['tab' => 'catalogo']) }}"
                               @if(request()->routeIs('admin.dashboard') && request('tab') === 'catalogo') aria-current="page" @endif
                               class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('admin.dashboard') && request('tab') === 'catalogo' ? 'bg-slate-800 text-accent-300 font-semibold border border-slate-700' : 'text-slate-300 hover:bg-slate-800/80 hover:text-paper' }}">
                                <x-icon name="tag" class="size-4 shrink-0" />
                                <span class="truncate">Catálogo &amp; Precificação</span>
                            </a>
                        </div>

                        <div class="space-y-1 pt-3 border-t border-slate-800">
                            <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Inteligência &amp; Relatórios</p>

                            <a href="{{ route('admin.dashboard', ['tab' => 'governanca']) }}"
                               @if(request()->routeIs('admin.dashboard') && request('tab') === 'governanca') aria-current="page" @endif
                               class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('admin.dashboard') && request('tab') === 'governanca' ? 'bg-slate-800 text-accent-300 font-semibold border border-slate-700' : 'text-slate-300 hover:bg-slate-800/80 hover:text-paper' }}">
                                <x-icon name="scale" class="size-4 shrink-0" />
                                <span class="truncate">Simulador DAS &amp; Auditoria</span>
                            </a>

                            <a href="{{ route('admin.reports.sales.csv') }}"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800/80 hover:text-paper transition">
                                <x-icon name="file-text" class="size-4 shrink-0" />
                                <span class="truncate">Exportar Vendas (CSV)</span>
                            </a>

                            <a href="{{ route('admin.reports.credits.csv') }}"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800/80 hover:text-paper transition">
                                <x-icon name="wallet" class="size-4 shrink-0" />
                                <span class="truncate">Exportar Saldos (CSV)</span>
                            </a>
                        </div>
                    </nav>
                </div>

                <div class="p-4 border-t border-slate-800 space-y-2">
                    <a href="{{ route('catalog.index') }}"
                       class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border border-slate-700 bg-slate-900 hover:bg-slate-800 text-paper text-xs font-medium transition">
                        <x-icon name="arrow-left" class="size-3.5" />
                        <span>Voltar ao Portal</span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-danger-300 hover:bg-slate-900 hover:text-danger-200 transition">
                            <x-icon name="log-out" class="size-3.5" />
                            <span>Sair da conta</span>
                        </button>
                    </form>
                </div>
            </aside>

            {{-- Main Content Column --}}
            <div class="flex-1 flex flex-col min-w-0">
                <header class="bg-surface border-b border-line px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4 sticky top-0 z-30">
                    <div class="flex items-center gap-3 min-w-0">
                        <button @click="sidebarOpen = true" type="button"
                                aria-label="Abrir menu administrativo"
                                class="md:hidden h-10 w-10 rounded-lg border border-line bg-paper text-ink hover:border-ink flex items-center justify-center shrink-0">
                            <x-icon name="menu" class="size-5" />
                        </button>

                        <nav aria-label="Localização atual" class="flex items-center gap-2 text-xs min-w-0">
                            <a href="{{ route('admin.dashboard') }}" class="text-slate-500 hover:text-ink font-medium hidden sm:inline shrink-0">
                                Backoffice NexoSupri
                            </a>
                            <span class="text-slate-400 hidden sm:inline">/</span>
                            <span class="font-semibold text-ink truncate">{{ $title }}</span>
                        </nav>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                        <span class="hidden lg:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-paper border border-line text-[11px] font-mono text-ink tabular-nums">
                            <span class="size-1.5 rounded-full bg-money-600"></span>
                            <span>Modelo A · 1 cr. = R$ 1,00</span>
                        </span>

                        @if($pendingPaymentsAlert > 0 && ! request()->routeIs('admin.payments.*'))
                            <a href="{{ route('admin.payments.index') }}"
                               class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-danger-200 bg-danger-50 text-danger-700 text-xs font-semibold hover:border-danger-400 transition">
                                <x-icon name="qr-code" class="size-3.5" />
                                <span class="tabular-nums">{{ $pendingPaymentsAlert }} pag.</span>
                            </a>
                        @endif

                        @if($pendingOrdersAlert > 0 && ! request()->routeIs('admin.orders.*'))
                            <a href="{{ route('admin.orders.index') }}"
                               class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-accent-200 bg-accent-50 text-accent-700 text-xs font-semibold hover:border-accent-400 transition">
                                <x-icon name="truck" class="size-3.5" />
                                <span class="tabular-nums">{{ $pendingOrdersAlert }} ped.</span>
                            </a>
                        @endif

                        <button type="button"
                                onclick="window.toggleColorScheme && window.toggleColorScheme()"
                                title="Alternar tema claro ou escuro"
                                aria-label="Alternar tema claro ou escuro"
                                class="h-9 w-9 rounded-lg border border-line bg-paper text-ink hover:border-ink flex items-center justify-center transition">
                            <span class="theme-toggle-moon items-center justify-center">
                                <x-icon name="moon" class="size-4" />
                            </span>
                            <span class="theme-toggle-sun items-center justify-center">
                                <x-icon name="sun" class="size-4" />
                            </span>
                        </button>
                    </div>
                </header>

                <main id="admin-main-content" tabindex="-1" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-[1600px] w-full mx-auto space-y-6 focus:outline-none">
                    {{-- Centralized Accessible Feedback (Nielsen Heuristic #1: Visibility of System Status) --}}
                    @foreach (['success', 'status'] as $flashKey)
                        @if (session($flashKey))
                            <div x-data="{ open: true }" x-show="open" role="status" aria-live="polite"
                                 class="flex items-start justify-between gap-3 rounded-xl border border-money-200 bg-money-50 px-4 py-3 text-xs sm:text-sm font-medium text-money-700">
                                <div class="flex items-start gap-2.5">
                                    <x-icon name="circle-check" class="size-5 shrink-0 mt-0.5" />
                                    <span>{{ session($flashKey) }}</span>
                                </div>
                                <button type="button" data-compact-btn @click="open = false" aria-label="Fechar aviso" class="text-money-700 hover:opacity-75 p-0.5">
                                    <x-icon name="x" class="size-4" />
                                </button>
                            </div>
                        @endif
                    @endforeach

                    @if (session('warning'))
                        <div x-data="{ open: true }" x-show="open" role="alert"
                             class="flex items-start justify-between gap-3 rounded-xl border border-accent-200 bg-accent-50 px-4 py-3 text-xs sm:text-sm font-medium text-accent-700">
                            <div class="flex items-start gap-2.5">
                                <x-icon name="alert-triangle" class="size-5 shrink-0 mt-0.5" />
                                <span>{{ session('warning') }}</span>
                            </div>
                            <button type="button" data-compact-btn @click="open = false" aria-label="Fechar alerta" class="text-accent-700 hover:opacity-75 p-0.5">
                                <x-icon name="x" class="size-4" />
                            </button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div x-data="{ open: true }" x-show="open" role="alert"
                             class="flex items-start justify-between gap-3 rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-xs sm:text-sm font-medium text-danger-700">
                            <div class="flex items-start gap-2.5">
                                <x-icon name="circle-alert" class="size-5 shrink-0 mt-0.5" />
                                <span>{{ session('error') }}</span>
                            </div>
                            <button type="button" data-compact-btn @click="open = false" aria-label="Fechar erro" class="text-danger-700 hover:opacity-75 p-0.5">
                                <x-icon name="x" class="size-4" />
                            </button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div role="alert" class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-xs sm:text-sm text-danger-700">
                            <p class="font-semibold flex items-center gap-2">
                                <x-icon name="circle-alert" class="size-4 shrink-0" />
                                <span>Verifique os campos informados antes de continuar:</span>
                            </p>
                            <ul class="mt-1.5 list-disc pl-6 space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
