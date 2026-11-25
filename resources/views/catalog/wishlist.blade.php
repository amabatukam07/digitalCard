<x-app-layout :metaTitle="'Itens Homologados (' . $products->count() . ')'" :metaDescription="'Insumos favoritos e homologados para reposição recorrente na NexoSupri.'">
    <div class="app-shell py-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-line">
            <div>
                <x-eyebrow>Padronização de compras</x-eyebrow>
                <h1 class="font-serif text-3xl text-ink mt-1">Itens Homologados ({{ $products->count() }})</h1>
                <p class="text-xs sm:text-sm text-slate-600 mt-1">Insumos padronizados salvos pela equipe para recompras rápidas.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                @if($products->isNotEmpty())
                    <form action="{{ route('wishlist.move-to-cart') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-md border border-line bg-surface hover:border-ink text-ink text-xs font-medium px-4 py-2.5 transition">
                            <x-icon name="shopping-cart" class="size-3.5 text-money-600" />
                            <span>Mover todos para o pedido</span>
                        </button>
                    </form>
                @endif

                <a href="{{ route('catalog.index') }}"
                   class="inline-flex items-center gap-2 bg-ink hover:bg-slate-800 text-paper text-xs font-medium px-4 py-2.5 rounded-md transition">
                    <span>Ver catálogo</span>
                    <x-icon name="arrow-right" class="size-3.5" />
                </a>
            </div>
        </div>

        @if($products->isEmpty())
            <div class="bg-surface rounded-lg border border-line p-12 text-center space-y-3">
                <div class="mx-auto w-10 h-10 rounded-full bg-paper border border-line text-slate-500 flex items-center justify-center">
                    <x-icon name="heart" class="size-5" />
                </div>
                <h3 class="font-serif text-xl text-ink">Nenhum item homologado salvo</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">Clique no ícone de coração nos insumos do catálogo para padronizar suas compras recorrentes.</p>
                <a href="{{ route('catalog.index') }}" class="inline-block bg-ink hover:bg-slate-800 text-paper font-medium text-xs px-5 py-2.5 rounded-md transition">
                    Ir para o catálogo
                </a>
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 xl:gap-5">
                @foreach($products as $product)
                    <x-product-card :product="$product" :wishlistIds="$wishlistIds ?? []" :eager="$loop->index < 4" />
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
