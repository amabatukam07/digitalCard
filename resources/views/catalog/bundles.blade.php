<x-app-layout :metaTitle="'Kits & Setups Prontos'" :metaDescription="'Combinações completas de escritório, criação de conteúdo, engenharia e mobilidade com cashback acumulado DigitalCard.'">
    <div class="app-shell py-8 space-y-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-line pb-5">
            <div>
                <x-eyebrow>Curadoria DigitalCard</x-eyebrow>
                <h1 class="mt-1 font-serif text-3xl sm:text-4xl text-ink flex items-center gap-2.5">
                    <x-icon name="layers" class="size-7 text-accent-600 shrink-0" />
                    <span>Kits &amp; Setups Prontos</span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 mt-1 max-w-2xl">
                    Adicione combinações completas ao carrinho com 1 clique, aproveite cupons recomendados e receba todo o cashback acumulado no seu Cartão Virtual.
                </p>
            </div>

            <a href="{{ route('catalog.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-4 py-2 text-xs font-medium text-ink transition shrink-0">
                <x-icon name="arrow-left" class="size-3.5" />
                <span>Explorar catálogo individual</span>
            </a>
        </div>

        @if($bundles->isEmpty())
            <div class="rounded-2xl border border-line bg-surface p-12 text-center space-y-3">
                <h2 class="font-serif text-2xl text-ink">Nenhum kit disponível no momento</h2>
                <a href="{{ route('catalog.index') }}" class="inline-block rounded-lg bg-ink px-5 py-2.5 text-xs font-semibold text-paper">
                    Ir para a loja
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($bundles as $bundle)
                    @php
                        $bundleTotal = $bundle->totalPrice();
                        $bundleCashback = $bundle->totalCashback();
                        $bundleAvailable = $bundle->isAvailable();
                    @endphp
                    <article class="bg-surface rounded-2xl border border-line p-6 flex flex-col justify-between gap-5 shadow-sm hover:border-slate-300 transition">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line pb-4">
                                <div class="space-y-1">
                                    @if($bundle->badge)
                                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 bg-paper border border-line text-[11px] font-semibold text-accent-700">
                                            <x-icon name="sparkles" class="size-3" />
                                            <span>{{ $bundle->badge }}</span>
                                        </span>
                                    @endif
                                    <h2 class="font-serif text-2xl text-ink">{{ $bundle->name }}</h2>
                                    @if($bundle->subtitle)
                                        <p class="text-xs text-slate-600 leading-relaxed">{{ $bundle->subtitle }}</p>
                                    @endif
                                </div>

                                @if($bundle->recommended_coupon_code)
                                    <span class="rounded-md bg-ink text-paper px-2.5 py-1 font-mono text-xs font-semibold shrink-0">
                                        Cupom {{ $bundle->recommended_coupon_code }}
                                    </span>
                                @endif
                            </div>

                            <div class="divide-y divide-line rounded-xl border border-line bg-paper/70 overflow-hidden">
                                @foreach($bundle->items as $bundleItem)
                                    @php($bProd = $bundleItem->product)
                                    @if($bProd)
                                        <div class="p-3 flex items-center justify-between gap-3 text-xs">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <a href="{{ route('catalog.product', $bProd->slug) }}" class="w-12 shrink-0 rounded border border-line bg-white overflow-hidden">
                                                    <x-product-image :product="$bProd" size="thumb" />
                                                </a>
                                                <div class="min-w-0">
                                                    <a href="{{ route('catalog.product', $bProd->slug) }}" class="font-medium text-ink hover:text-accent-600 line-clamp-1">
                                                        {{ $bundleItem->quantity }}× {{ $bProd->name }}
                                                    </a>
                                                    <span class="text-[11px] text-slate-500">
                                                        {{ $bProd->category?->name ?? 'Catálogo' }} · {{ $bProd->stock > 0 ? $bProd->stock.' un. disponíveis' : 'Sem estoque' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <span class="font-mono tabular-nums font-semibold text-ink shrink-0">
                                                R$ {{ number_format((float) $bProd->price * $bundleItem->quantity, 2, ',', '.') }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div class="pt-4 border-t border-line flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <span class="text-xs text-slate-500 block">Valor do setup ({{ $bundle->items->count() }} itens)</span>
                                <div class="flex flex-wrap items-baseline gap-2.5 mt-0.5">
                                    <span class="font-mono text-2xl font-bold tabular-nums text-ink">
                                        R$ {{ number_format($bundleTotal, 2, ',', '.') }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded bg-money-50 border border-money-200 px-2 py-0.5 text-xs font-semibold text-money-700 tabular-nums">
                                        + R$ {{ number_format($bundleCashback, 2, ',', '.') }} cashback
                                    </span>
                                </div>
                            </div>

                            <form action="{{ route('bundles.add-to-cart', $bundle->slug) }}" method="POST" class="shrink-0">
                                @csrf
                                <button type="submit"
                                        {{ $bundleAvailable ? '' : 'disabled' }}
                                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-ink hover:bg-slate-800 disabled:bg-slate-200 disabled:text-slate-500 px-5 py-3 text-xs font-semibold text-paper transition">
                                    <x-icon name="shopping-cart" class="size-4" />
                                    <span>{{ $bundleAvailable ? 'Adicionar kit completo ao carrinho' : 'Kit parcialmente indisponível' }}</span>
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
