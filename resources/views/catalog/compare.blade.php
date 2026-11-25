<x-app-layout :metaTitle="'Comparar Insumos'" :metaDescription="'Compare até 4 insumos NexoSupri lado a lado por preço, apresentação, disponibilidade e avaliações.'">
    <div class="app-shell py-8 space-y-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-line pb-5">
            <div>
                <x-eyebrow>Comparador B2B</x-eyebrow>
                <h1 class="mt-1 font-serif text-3xl text-ink flex items-center gap-2.5">
                    <x-icon name="scale" class="size-7 text-accent-600 shrink-0" />
                    <span>Comparar Insumos Lado a Lado</span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 mt-1">
                    Analise valor em R$/créditos, apresentação, disponibilidade e avaliações de até 4 insumos ({{ $products->count() }}/4 selecionados).
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('catalog.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface hover:border-ink px-4 py-2 text-xs font-medium text-ink transition">
                    <x-icon name="arrow-left" class="size-3.5" />
                    <span>Voltar ao catálogo</span>
                </a>

                @if($products->isNotEmpty())
                    <form action="{{ route('compare.clear') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-danger-200 bg-danger-50 hover:bg-danger-100 px-4 py-2 text-xs font-semibold text-danger-700 transition">
                            <x-icon name="trash-2" class="size-3.5" />
                            <span>Limpar comparador</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if($products->isEmpty())
            <div class="rounded-2xl border border-line bg-surface p-10 text-center space-y-4">
                <div class="mx-auto h-12 w-12 rounded-xl bg-paper border border-line flex items-center justify-center text-slate-600">
                    <x-icon name="scale" class="size-6" />
                </div>
                <div class="space-y-1 max-w-md mx-auto">
                    <h2 class="font-serif text-2xl text-ink">Seu comparador está vazio</h2>
                    <p class="text-xs sm:text-sm text-slate-600">
                        Clique no ícone de balança nos cards de insumos para comparar preços, apresentações e disponibilidade lado a lado.
                    </p>
                </div>
                <a href="{{ route('catalog.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-ink hover:bg-slate-800 px-5 py-2.5 text-xs font-semibold text-paper transition">
                    <span>Explorar catálogo</span>
                    <x-icon name="arrow-right" class="size-4" />
                </a>
            </div>
        @else
            <div tabindex="0" role="region" aria-label="Tabela comparativa de insumos" class="table-scroll-region rounded-2xl border border-line bg-surface shadow-sm">
                <table class="w-full min-w-[720px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-line bg-paper">
                            <th class="w-44 px-5 py-4 text-xs font-semibold text-slate-600 align-top">Critério</th>
                            @foreach($products as $item)
                                <th class="px-5 py-4 align-top min-w-[210px] border-l border-line">
                                    <div class="space-y-3">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-[11px] font-medium text-slate-500 truncate">{{ $item->category?->name ?? 'Catálogo' }}</span>
                                            <form action="{{ route('compare.remove', $item) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Remover item" aria-label="Remover {{ $item->name }} do comparador"
                                                        class="text-xs text-slate-400 hover:text-danger-600 transition p-1 -m-1">
                                                    <x-icon name="x" class="size-4" />
                                                </button>
                                            </form>
                                        </div>

                                        <a href="{{ route('catalog.product', $item->slug) }}" class="block max-w-[140px] mx-auto">
                                            <x-product-image :product="$item" size="card" />
                                        </a>

                                        <a href="{{ route('catalog.product', $item->slug) }}" class="block text-sm font-semibold text-ink hover:text-accent-600 transition line-clamp-2 leading-snug">
                                            {{ $item->name }}
                                        </a>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line text-xs">
                        <tr>
                            <th scope="row" class="px-5 py-4 font-semibold text-slate-700 bg-paper/60">Preço / Créditos</th>
                            @foreach($products as $item)
                                @php($isLowest = $lowestPrice !== null && abs((float) $item->price - (float) $lowestPrice) < 0.01)
                                <td class="px-5 py-4 border-l border-line {{ $isLowest ? 'bg-money-50/40' : '' }}">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-base font-bold tabular-nums text-ink">
                                            R$ {{ number_format((float) $item->price, 2, ',', '.') }}
                                        </span>
                                        @if($isLowest && $products->count() > 1)
                                            <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 bg-money-600 text-paper text-[11px] font-semibold">
                                                <x-icon name="check" class="size-3" />
                                                <span>Menor preço</span>
                                            </span>
                                        @endif
                                    </div>
                                    <span class="block mt-1 font-mono text-[11px] text-slate-500">{{ number_format((float) $item->price, 2, ',', '.') }} créditos</span>
                                </td>
                            @endforeach
                        </tr>

                        <tr>
                            <th scope="row" class="px-5 py-4 font-semibold text-slate-700 bg-paper/60">Apresentação / SKU</th>
                            @foreach($products as $item)
                                <td class="px-5 py-4 border-l border-line">
                                    <span class="font-medium text-ink block">{{ $item->presentation ?: $item->unit }}</span>
                                    <span class="font-mono text-[11px] text-slate-500">{{ $item->sku }}</span>
                                </td>
                            @endforeach
                        </tr>

                        <tr>
                            <th scope="row" class="px-5 py-4 font-semibold text-slate-700 bg-paper/60">Avaliação média</th>
                            @foreach($products as $item)
                                @php
                                    $rating = (float) $item->rating;
                                    $reviewsCount = (int) $item->reviews_count;
                                @endphp
                                <td class="px-5 py-4 border-l border-line">
                                    @if($reviewsCount > 0)
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <x-icon name="star" class="size-4 text-accent-500" />
                                            <span class="font-semibold tabular-nums text-ink">{{ number_format($rating, 1, ',', '.') }}</span>
                                            <span class="text-slate-500 tabular-nums">({{ $reviewsCount }})</span>
                                        </div>
                                    @else
                                        <span class="text-slate-500">Sem avaliações</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>

                        <tr>
                            <th scope="row" class="px-5 py-4 font-semibold text-slate-700 bg-paper/60">Disponibilidade</th>
                            @foreach($products as $item)
                                <td class="px-5 py-4 border-l border-line">
                                    <span class="inline-flex items-center gap-1.5 font-medium {{ $item->stock > 0 ? 'text-money-700' : 'text-danger-700' }}">
                                        <span class="size-2 rounded-full {{ $item->stock > 0 ? 'bg-money-500' : 'bg-danger-500' }}"></span>
                                        <span>{{ $item->stock > 0 ? $item->stock.' em estoque' : 'Indisponível' }}</span>
                                    </span>
                                </td>
                            @endforeach
                        </tr>

                        <tr>
                            <th scope="row" class="px-5 py-4 font-semibold text-slate-700 bg-paper/60">Ações</th>
                            @foreach($products as $item)
                                <td class="px-5 py-4 border-l border-line">
                                    <div class="flex flex-col gap-2">
                                        <form action="{{ route('cart.add', $item) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit"
                                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 text-paper font-semibold px-3 py-2 transition">
                                                <x-icon name="shopping-cart" class="size-3.5" />
                                                <span>Adicionar ao pedido</span>
                                            </button>
                                        </form>
                                        <a href="{{ route('catalog.product', $item->slug) }}"
                                           class="w-full text-center rounded-lg border border-line bg-paper hover:border-ink text-ink font-medium px-3 py-2 transition">
                                            Ver detalhes
                                        </a>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        @if($suggestions->isNotEmpty() && $products->count() < 4)
            <section class="space-y-4 pt-4 border-t border-line">
                <div>
                    <x-eyebrow>Sugestões para comparar</x-eyebrow>
                    <h2 class="font-serif text-2xl text-ink mt-1">Adicione mais insumos ao comparador</h2>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-5">
                    @foreach($suggestions as $suggested)
                        <x-product-card :product="$suggested" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
