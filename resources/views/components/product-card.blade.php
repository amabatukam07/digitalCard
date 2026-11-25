@props(['product', 'wishlistIds' => [], 'compareIds' => null, 'eager' => false])

@php
    $isWishlisted = in_array($product->id, $wishlistIds ?? [], true);
    $resolvedCompareIds = is_array($compareIds) ? $compareIds : array_map('intval', session('compare_product_ids', []));
    $isCompared = in_array($product->id, $resolvedCompareIds, true);
    $reviewsCount = (int) $product->reviews_count;
    $rating = (float) $product->rating;
@endphp

<article class="bg-surface rounded-xl border border-line hover:border-ink/40 hover:-translate-y-1 hover:shadow-float transition-all duration-300 ease-out flex flex-col overflow-hidden group">
    <div class="relative border-b border-line bg-surface overflow-hidden">
        <x-product-carousel :product="$product" mode="card" :priority="$eager" :href="route('catalog.product', $product->slug)" />

        @if($product->discount_percent > 0)
            <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md bg-accent-600 text-paper text-xs font-semibold tabular-nums shadow-sm pointer-events-none">
                −{{ $product->discount_percent }}%
            </span>
        @elseif($product->sku)
            <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md bg-ink/85 text-paper font-mono text-[10px] font-medium pointer-events-none">
                {{ $product->sku }}
            </span>
        @endif

        <div class="absolute top-2.5 right-2.5 z-10 flex flex-col gap-1.5">
            <form action="{{ route('wishlist.toggle', $product) }}" method="POST">
                @csrf
                <button type="submit"
                        data-compact-btn
                        title="{{ $isWishlisted ? 'Remover dos itens homologados' : 'Salvar como item homologado' }}"
                        aria-label="{{ $isWishlisted ? 'Remover '.$product->name.' dos itens homologados' : 'Salvar '.$product->name.' nos itens homologados' }}"
                        class="h-8 w-8 rounded-full bg-surface border border-line hover:border-ink hover:scale-110 active:scale-95 flex items-center justify-center transition-all duration-200 shadow-sm {{ $isWishlisted ? 'text-danger-600 border-danger-200 bg-danger-50' : 'text-slate-600 hover:text-ink' }}">
                    <x-icon name="heart" class="size-4" />
                </button>
            </form>

            <form action="{{ route('compare.toggle', $product) }}" method="POST">
                @csrf
                <button type="submit"
                        data-compact-btn
                        title="{{ $isCompared ? 'Remover do comparador' : 'Comparar insumo' }}"
                        aria-label="{{ $isCompared ? 'Remover '.$product->name.' do comparador' : 'Adicionar '.$product->name.' ao comparador' }}"
                        aria-pressed="{{ $isCompared ? 'true' : 'false' }}"
                        class="h-8 w-8 rounded-full border hover:scale-110 active:scale-95 flex items-center justify-center transition-all duration-200 shadow-sm {{ $isCompared ? 'bg-ink text-paper border-ink' : 'bg-surface border-line text-slate-600 hover:text-ink hover:border-ink' }}">
                    <x-icon name="scale" class="size-4" />
                </button>
            </form>
        </div>
    </div>

    <div class="p-3.5 sm:p-4 flex-1 flex flex-col justify-between gap-2.5">
        <div class="space-y-1.5">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[11px] font-medium text-slate-600 bg-paper border border-line/80 px-2 py-0.5 rounded-md truncate max-w-[68%]">
                    {{ $product->category->name ?? 'Catálogo' }}
                </span>

                @if($reviewsCount > 0)
                    <div class="flex items-center gap-1 shrink-0" title="{{ $reviewsCount }} {{ $reviewsCount === 1 ? 'avaliação' : 'avaliações' }}">
                        <x-icon name="star" class="size-3.5 text-accent-500" />
                        <span class="text-xs tabular-nums font-medium text-ink">{{ number_format($rating, 1, ',', '.') }}</span>
                        <span class="text-xs text-slate-400 tabular-nums">({{ $reviewsCount }})</span>
                    </div>
                @endif
            </div>

            <h3 class="text-sm font-medium text-ink group-hover:text-accent-600 transition-colors line-clamp-2 leading-snug min-h-[2.5rem]">
                <a href="{{ route('catalog.product', $product->slug) }}">
                    {{ $product->name }}
                </a>
            </h3>

            @if($product->presentation || $product->brand)
                <p class="text-[11px] text-slate-500 truncate">
                    {{ $product->presentation ?: $product->unit }}{{ $product->brand ? ' · '.$product->brand : '' }}
                </p>
            @endif
        </div>

        <div class="pt-2.5 border-t border-line space-y-2.5">
            <div class="space-y-1">
                <div class="flex items-baseline flex-wrap gap-x-2 gap-y-0.5">
                    <span class="text-base sm:text-lg font-semibold text-ink tabular-nums whitespace-nowrap">
                        R$ {{ number_format((float) $product->price, 2, ',', '.') }}
                    </span>

                    @if($product->discount_percent > 0)
                        <span class="text-xs text-slate-400 line-through tabular-nums whitespace-nowrap">
                            R$ {{ number_format((float) $product->compare_at_price, 2, ',', '.') }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-1 flex-wrap">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-paper border border-line text-[11px] font-mono font-medium text-ink tabular-nums">
                        {{ number_format((float) $product->price, 2, ',', '.') }} créd.
                    </span>

                    @if($product->is_low_stock)
                        <span class="text-[11px] font-medium text-accent-700 tabular-nums">
                            Estoque: {{ (int) $product->stock }}
                        </span>
                    @endif
                </div>
            </div>

            <form action="{{ route('cart.add', $product) }}" method="POST">
                @csrf
                <input type="hidden" name="quantity" value="1">
                <button type="submit"
                        class="w-full py-2 px-3 rounded-lg border border-line bg-paper text-ink group-hover:bg-ink group-hover:text-paper group-hover:border-ink hover:bg-slate-800 active:scale-[0.98] font-medium text-xs flex items-center justify-center gap-1.5 transition-all duration-200 whitespace-nowrap">
                    <x-icon name="shopping-cart" class="size-3.5 transition-transform duration-200 group-hover:scale-110 shrink-0" />
                    <span>Adicionar ao pedido</span>
                </button>
            </form>
        </div>
    </div>
</article>
