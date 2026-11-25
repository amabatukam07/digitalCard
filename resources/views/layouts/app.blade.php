@props([
    'metaTitle' => null,
    'metaDescription' => 'NexoSupri — Plataforma de abastecimento empresarial com créditos pré-pagos. Sua empresa cuida dos negócios. A NexoSupri cuida das compras.',
    'metaImage' => null,
    'noindex' => false,
])
@php
    $shouldNoIndex = $noindex || request()->routeIs('cart.*', 'wallet.*', 'company.*', 'orders.*', 'profile.*', 'admin.*', 'login', 'register', 'password.*', 'verification.*');
    $resolvedTitle = $metaTitle
        ? $metaTitle.' — '.config('app.name', 'NexoSupri')
        : config('app.name', 'NexoSupri').' — Abastecimento Empresarial com Créditos Pré-Pagos';
    $resolvedImage = $metaImage ?: asset('favicon.ico');
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

        <title>{{ $resolvedTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

        @if($shouldNoIndex)
            <meta name="robots" content="noindex, nofollow">
        @else
            <meta name="robots" content="index, follow">
        @endif

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="NexoSupri">
        <meta property="og:title" content="{{ $resolvedTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ $resolvedImage }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $resolvedTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $resolvedImage }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @stack('head')

        <style>
            [x-cloak] { display: none !important; }
            .table-scroll-region {
                overflow: auto;
                max-inline-size: 100%;
            }
            .table-scroll-region table {
                border-collapse: separate;
                border-spacing: 0;
            }
            .table-scroll-region thead {
                position: sticky;
                inset-block-start: 0;
                z-index: 1;
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
    <body class="min-h-full flex flex-col font-sans antialiased text-slate-900 bg-paper overflow-x-clip selection:bg-accent-400 selection:text-slate-950">
        <a href="#main-content"
           class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-xl focus:bg-slate-950 focus:px-4 focus:py-2.5 focus:text-xs focus:font-bold focus:text-accent-400 focus:shadow-lg">
            Pular para o conteúdo principal
        </a>

        @if (session('success') || session('error') || session('warning') || $errors->any())
            <div x-data="{ visible: true }"
                 x-init="setTimeout(() => visible = false, 6000)"
                 x-show="visible"
                 x-cloak
                 role="status"
                 aria-live="polite"
                 class="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:bottom-5 sm:right-5 z-50 sm:max-w-md sm:w-full pointer-events-auto">
                @if (session('success'))
                    <div class="flex items-start gap-3.5 rounded-lg border border-line bg-ink p-4 text-paper shadow-float">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-money-600 text-paper" aria-hidden="true">
                            <x-icon name="check" class="size-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-money-300">Operação confirmada</p>
                            <p class="text-xs text-slate-200 mt-0.5 leading-relaxed">{{ session('success') }}</p>
                        </div>
                        <button @click="visible = false" type="button" aria-label="Fechar notificação" class="text-slate-300 hover:text-white p-1 min-h-[44px] min-w-[44px] flex items-center justify-center">
                            <x-icon name="x" class="size-4" />
                        </button>
                    </div>
                @elseif (session('warning'))
                    <div class="flex items-start gap-3.5 rounded-lg border border-line bg-ink p-4 text-paper shadow-float">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-700 text-paper" aria-hidden="true">
                            <x-icon name="circle-alert" class="size-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-slate-200">Atenção</p>
                            <p class="text-xs text-slate-200 mt-0.5 leading-relaxed">{{ session('warning') }}</p>
                        </div>
                        <button @click="visible = false" type="button" aria-label="Fechar notificação" class="text-slate-300 hover:text-white p-1 min-h-[44px] min-w-[44px] flex items-center justify-center">
                            <x-icon name="x" class="size-4" />
                        </button>
                    </div>
                @else
                    <div class="flex items-start gap-3.5 rounded-lg border border-line bg-ink p-4 text-paper shadow-float">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-danger-600 text-white" aria-hidden="true">
                            <x-icon name="x" class="size-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-danger-300">Verifique os dados</p>
                            <p class="text-xs text-slate-200 mt-0.5 leading-relaxed">{{ session('error') ?? $errors->first() }}</p>
                        </div>
                        <button @click="visible = false" type="button" aria-label="Fechar notificação" class="text-slate-300 hover:text-white p-1 min-h-[44px] min-w-[44px] flex items-center justify-center">
                            <x-icon name="x" class="size-4" />
                        </button>
                    </div>
                @endif
            </div>
        @endif

        @include('layouts.navigation')

        @isset($header)
            <header class="bg-surface border-b border-line">
                <div class="app-shell py-5">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main id="main-content" class="flex-1">
            {{ $slot }}
        </main>

        @if(($compareCount ?? 0) > 0 && ! request()->routeIs('compare.index'))
            <div class="sticky bottom-4 z-30 pointer-events-none px-4 mt-4">
                <div class="app-shell">
                    <div class="pointer-events-auto mx-auto max-w-xl rounded-xl border border-slate-700 bg-ink text-paper px-4 py-3 shadow-float flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 text-xs">
                            <span class="h-8 w-8 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center shrink-0">
                                <x-icon name="scale" class="size-4 text-accent-400" />
                            </span>
                            <div>
                                <p class="font-semibold text-paper tabular-nums">
                                    Comparador: {{ $compareCount }}/4 {{ $compareCount === 1 ? 'insumo' : 'insumos' }}
                                </p>
                                <p class="text-slate-300 text-[11px]">Compare preços, apresentações e disponibilidade lado a lado.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <form action="{{ route('compare.clear') }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-md border border-slate-700 text-xs font-medium text-slate-300 hover:text-paper hover:border-slate-500 transition">
                                    Limpar
                                </button>
                            </form>
                            <a href="{{ route('compare.index') }}"
                               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-md bg-paper text-ink hover:bg-slate-200 text-xs font-semibold transition">
                                <span>Comparar agora</span>
                                <x-icon name="arrow-right" class="size-3.5" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <footer class="site-footer bg-ink text-slate-300 mt-12 border-t border-slate-800">
            <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" type="button"
                    class="w-full bg-ink hover:bg-slate-900 text-slate-200 text-xs font-semibold py-3.5 text-center transition border-b border-slate-800/80 min-h-[44px]">
                Voltar ao início da página ↑
            </button>

            <div class="app-shell py-12">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-8 pb-10 border-b border-slate-800">
                    <div class="lg:col-span-2 space-y-4 pr-4">
                        <x-application-logo :dark="true" />
                        <p class="text-xs text-slate-300 leading-relaxed max-w-sm">
                            {{ config('nexosupri.slogan') }} Centralize insumos de escritório, limpeza, higiene, copa e bebidas com créditos pré-pagos exclusivos (Modelo A: 1 crédito = R$ 1,00).
                        </p>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-white mb-3.5">Categorias B2B</h4>
                        <ul class="space-y-2 text-xs text-slate-300">
                            @forelse($navCategories ?? [] as $footerCat)
                                <li>
                                    <a href="{{ route('catalog.category', $footerCat['slug']) }}" class="hover:text-accent-400 transition">
                                        {{ $footerCat['name'] }}
                                    </a>
                                </li>
                            @empty
                                <li><a href="{{ route('catalog.index') }}" class="hover:text-accent-400 transition">Catálogo completo</a></li>
                            @endforelse
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-white mb-3.5">Portal Empresarial</h4>
                        <ul class="space-y-2 text-xs text-slate-300">
                            <li><a href="{{ route('plans.index') }}" class="hover:text-accent-400 transition">Planos e Benefícios</a></li>
                            @auth
                                @if($currentCompany ?? false)
                                    <li><a href="{{ route('company.dashboard') }}" class="hover:text-accent-400 transition">Painel da Empresa</a></li>
                                    <li><a href="{{ route('wallet.show') }}" class="hover:text-accent-400 transition">Carteira de Créditos</a></li>
                                    <li><a href="{{ route('orders.index') }}" class="hover:text-accent-400 transition">Pedidos Corporativos</a></li>
                                    <li><a href="{{ route('wishlist.index') }}" class="hover:text-accent-400 transition">Itens Homologados</a></li>
                                @elseif(auth()->user()?->hasAdminAccess())
                                    <li><a href="{{ route('admin.dashboard') }}" class="hover:text-accent-400 transition">Painel Administrativo</a></li>
                                    <li><a href="{{ route('admin.payments.index') }}" class="hover:text-accent-400 transition">Pagamentos &amp; Créditos</a></li>
                                    <li><a href="{{ route('admin.orders.index') }}" class="hover:text-accent-400 transition">Pedidos B2B</a></li>
                                    <li><a href="{{ route('admin.suppliers.index') }}" class="hover:text-accent-400 transition">Fornecedores &amp; Estoque</a></li>
                                @endif
                            @else
                                <li><a href="{{ route('login') }}" class="hover:text-accent-400 transition">Entrar no Portal</a></li>
                                <li><a href="{{ route('register') }}" class="hover:text-accent-400 transition">Cadastrar Empresa</a></li>
                                <li><a href="{{ route('bundles.index') }}" class="hover:text-accent-400 transition">Kits de Abastecimento</a></li>
                            @endauth
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-white mb-3.5">Fluxo do Pedido (Modelo A)</h4>
                        @php
                            $footerOrderSteps = [
                                1 => \App\Enums\OrderStatus::AwaitingApproval,
                                2 => \App\Enums\OrderStatus::Requested,
                                3 => \App\Enums\OrderStatus::Separating,
                                4 => \App\Enums\OrderStatus::Shipped,
                                5 => \App\Enums\OrderStatus::Delivered,
                            ];
                        @endphp
                        <ul class="space-y-1.5 text-xs text-slate-300">
                            @foreach($footerOrderSteps as $stepNum => $statusCase)
                                <li class="p-2 rounded-md bg-slate-900 border border-slate-800 flex items-center justify-between">
                                    <span class="font-medium {{ $statusCase === \App\Enums\OrderStatus::Delivered ? 'text-money-400' : 'text-slate-200' }}">
                                        {{ $stepNum }}. {{ $statusCase->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="pt-6 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-300 gap-4">
                    <p>© {{ date('Y') }} NexoSupri. Abastecimento empresarial com controle, praticidade e previsibilidade.</p>
                    <p class="text-[11px] text-slate-400">Modelo A: 1 crédito = R$ 1,00 · Crédito exclusivo sem saque ou transferência.</p>
                </div>
            </div>
        </footer>
    </body>
</html>
