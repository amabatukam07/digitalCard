@php
    $pageTitle = $selectedCategory ? $selectedCategory->name : (request('q') ? 'Busca: ' . request('q') : 'Loja Digital com Cartão Virtual e Cashback');
    $pageDesc = $selectedCategory
        ? 'Produtos na categoria ' . $selectedCategory->name . ' com pagamento via cartão virtual e cashback na entrega.'
        : 'Loja digital com cartão virtual integrado: recarregue por PIX, compre com o saldo e ganhe cashback quando o pedido for entregue.';
    $userCard = auth()->check() ? auth()->user()->virtualCard : null;
@endphp

<x-app-layout :metaTitle="$pageTitle" :metaDescription="$pageDesc">
    <div x-data="{ mobileFiltersOpen: false }" class="app-shell py-6 space-y-8">

        @if($isHomeStorefront)
            <section class="bg-surface rounded-2xl border border-line p-6 sm:p-8 xl:p-10 shadow-sm">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 xl:gap-12 items-center">
                    <div class="lg:col-span-7 space-y-4">
                        <x-eyebrow>Cartão virtual &amp; cashback</x-eyebrow>
                        <h1 class="font-serif hero-display text-ink">
                            Compre com o saldo do cartão. Receba parte de volta.
                        </h1>
                        <p class="text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                            Recarregue via PIX em segundos, pague com segurança pelo seu cartão virtual e receba cashback direto na carteira assim que o pedido for entregue.
                        </p>
                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <a href="#catalogo"
                               class="inline-flex items-center gap-2 bg-ink hover:bg-slate-800 active:scale-[0.98] text-paper text-sm font-medium px-5 py-2.5 rounded-lg transition-all duration-200 shadow-sm">
                                <span>Ver produtos</span>
                                <x-icon name="arrow-right" class="size-4" />
                            </a>
                            <a href="#como-funciona"
                               class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg border border-line bg-paper hover:border-ink text-sm font-medium text-ink transition-colors duration-200">
                                <span>Como funciona</span>
                            </a>
                            @auth
                                <a href="{{ route('card.show') }}" class="text-xs font-medium text-money-600 hover:underline ml-1">
                                    Acessar carteira e PIX →
                                </a>
                            @else
                                <a href="{{ route('register') }}" class="text-xs font-medium text-ink hover:underline ml-1">
                                    Criar conta gratuita →
                                </a>
                            @endauth
                        </div>
                    </div>

                    <div class="lg:col-span-5 max-w-[440px] mx-auto lg:ml-auto lg:mr-0 w-full">
                        <x-virtual-card :card="$userCard" :holderName="auth()->user()?->name" />
                    </div>
                </div>

                <div id="como-funciona" class="mt-8 pt-6 border-t border-line grid grid-cols-1 md:grid-cols-3 gap-4 xl:gap-6">
                    <div class="flex items-start gap-3.5 p-3.5 sm:p-4 rounded-xl bg-paper border border-line hover:border-slate-300 transition-colors">
                        <div class="h-9 w-9 rounded-lg bg-ink text-paper flex items-center justify-center font-serif text-lg shrink-0">
                            1
                        </div>
                        <div class="space-y-0.5">
                            <h2 class="font-serif text-lg text-ink leading-snug">1. Recarregue por PIX</h2>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Gere o QR Code ou código copia e cola na sua carteira para carregar o saldo do cartão na hora.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-paper border border-line hover:border-slate-300 transition-colors">
                        <div class="h-9 w-9 rounded-lg bg-ink text-paper flex items-center justify-center font-serif text-lg shrink-0">
                            2
                        </div>
                        <div class="space-y-0.5">
                            <h2 class="font-serif text-lg text-ink leading-snug">2. Compre com o saldo</h2>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Adicione itens ao carrinho, calcule o frete por CEP e feche o pedido com seu PIN.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-paper border border-line hover:border-slate-300 transition-colors">
                        <div class="h-9 w-9 rounded-lg bg-money-600 text-paper flex items-center justify-center font-serif text-lg shrink-0">
                            3
                        </div>
                        <div class="space-y-0.5">
                            <h2 class="font-serif text-lg text-ink leading-snug">3. Receba cashback</h2>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Quando o pedido passa para “Produto entregue”, o valor volta direto para o saldo.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section aria-label="Diferenciais DigitalCard" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div class="bg-surface rounded-xl border border-line p-4 flex items-center gap-3 shadow-sm">
                    <span class="h-9 w-9 rounded-lg bg-paper border border-line flex items-center justify-center shrink-0">
                        <x-icon name="sparkles" class="size-4 text-accent-600" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-ink">Curadoria ampliada</p>
                        <p class="text-[11px] text-slate-600 truncate">75+ itens de hardware e setup</p>
                    </div>
                </div>

                <div class="bg-surface rounded-xl border border-line p-4 flex items-center gap-3 shadow-sm">
                    <span class="h-9 w-9 rounded-lg bg-money-50 border border-money-200 flex items-center justify-center shrink-0">
                        <x-icon name="wallet" class="size-4 text-money-700" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-ink">Cashback na carteira</p>
                        <p class="text-[11px] text-slate-600 truncate">Crédito automático na entrega</p>
                    </div>
                </div>

                <div class="bg-surface rounded-xl border border-line p-4 flex items-center gap-3 shadow-sm">
                    <span class="h-9 w-9 rounded-lg bg-paper border border-line flex items-center justify-center shrink-0">
                        <x-icon name="crown" class="size-4 text-accent-600" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-ink">Clube DigitalCard</p>
                        <p class="text-[11px] text-slate-600 truncate">Até +3,5% cashback e frete grátis</p>
                    </div>
                </div>

                <div class="bg-surface rounded-xl border border-line p-4 flex items-center gap-3 shadow-sm">
                    <span class="h-9 w-9 rounded-lg bg-paper border border-line flex items-center justify-center shrink-0">
                        <x-icon name="send" class="size-4 text-ink" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-ink">PIX, P2P &amp; Gift Cards</p>
                        <p class="text-[11px] text-slate-600 truncate">Envie saldo em segundos por e-mail</p>
                    </div>
                </div>
            </section>

            @if($departmentCards->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <x-eyebrow>Departamentos</x-eyebrow>
                            <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5">Explore por categoria</h2>
                        </div>
                        <span class="text-xs text-slate-500 tabular-nums">{{ $departmentCards->count() }} departamentos</span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
                        @foreach($departmentCards as $dept)
                            <a href="{{ route('catalog.category', $dept['slug']) }}"
                               class="group bg-surface rounded-xl border border-line hover:border-ink p-4 flex items-center gap-3.5 transition-all hover:shadow-sm">
                                <span class="h-10 w-10 rounded-lg bg-paper border border-line group-hover:bg-ink group-hover:text-paper flex items-center justify-center shrink-0 transition-colors">
                                    <x-icon :name="$dept['icon']" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-xs sm:text-sm font-semibold text-ink group-hover:text-accent-600 truncate">{{ $dept['name'] }}</h3>
                                    <p class="text-[11px] text-slate-500 tabular-nums mt-0.5">
                                        {{ $dept['products_count'] }} {{ $dept['products_count'] === 1 ? 'produto' : 'produtos' }}
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($featuredDeals->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <x-eyebrow>Oferta</x-eyebrow>
                            <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5">Destaques em oferta</h2>
                        </div>
                        <a href="{{ route('catalog.index', ['sort' => 'maior_desconto']) }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-ink hover:text-accent-600 transition-colors">
                            <span>Ver todas as ofertas</span>
                            <x-icon name="arrow-right" class="size-3.5" />
                        </a>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-5">
                        @foreach($featuredDeals as $dealProduct)
                            <x-product-card :product="$dealProduct" :wishlistIds="$wishlistIds" :eager="$loop->index < 4" />
                        @endforeach
                    </div>
                </section>
            @endif

            @if($topCashbackProducts->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <x-eyebrow>Maior retorno</x-eyebrow>
                            <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5">Cashback turbinado</h2>
                        </div>
                        <a href="{{ route('catalog.index', ['sort' => 'maior_cashback']) }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-money-700 hover:underline">
                            <span>Ver maior cashback</span>
                            <x-icon name="arrow-right" class="size-3.5" />
                        </a>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-5">
                        @foreach($topCashbackProducts as $cbProduct)
                            <x-product-card :product="$cbProduct" :wishlistIds="$wishlistIds" />
                        @endforeach
                    </div>
                </section>
            @endif

            @if(isset($featuredBundles) && $featuredBundles->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <x-eyebrow>Setups prontos</x-eyebrow>
                            <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5 flex items-center gap-2">
                                <x-icon name="layers" class="size-6 text-accent-600" />
                                <span>Kits &amp; combinações curadas</span>
                            </h2>
                        </div>
                        <a href="{{ route('bundles.index') }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-ink hover:text-accent-600 transition-colors">
                            <span>Ver todos os kits</span>
                            <x-icon name="arrow-right" class="size-3.5" />
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 xl:gap-5">
                        @foreach($featuredBundles as $homeBundle)
                            @php
                                $bTotal = $homeBundle->totalPrice();
                                $bCashback = $homeBundle->totalCashback();
                            @endphp
                            <article class="bg-surface rounded-2xl border border-line hover:border-ink p-5 flex flex-col justify-between gap-4 transition shadow-sm">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between gap-2">
                                        @if($homeBundle->badge)
                                            <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 bg-paper border border-line text-[11px] font-semibold text-accent-700">
                                                <x-icon name="sparkles" class="size-3" />
                                                <span>{{ $homeBundle->badge }}</span>
                                            </span>
                                        @endif
                                        @if($homeBundle->recommended_coupon_code)
                                            <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded bg-ink text-paper">
                                                {{ $homeBundle->recommended_coupon_code }}
                                            </span>
                                        @endif
                                    </div>

                                    <div>
                                        <h3 class="font-serif text-xl text-ink">{{ $homeBundle->name }}</h3>
                                        @if($homeBundle->subtitle)
                                            <p class="text-xs text-slate-600 mt-1 line-clamp-2">{{ $homeBundle->subtitle }}</p>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2 pt-1 overflow-x-auto no-scrollbar">
                                        @foreach($homeBundle->items as $hbItem)
                                            @if($hbItem->product)
                                                <a href="{{ route('catalog.product', $hbItem->product->slug) }}"
                                                   title="{{ $hbItem->product->name }}"
                                                   class="w-12 h-12 shrink-0 rounded-lg border border-line bg-white overflow-hidden p-1 hover:border-ink transition">
                                                    <img src="{{ $hbItem->product->thumbnail_url }}" alt="{{ $hbItem->product->name }}" class="w-full h-full object-contain">
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-line flex items-center justify-between gap-3">
                                    <div>
                                        <span class="font-mono text-lg font-bold tabular-nums text-ink block">R$ {{ number_format($bTotal, 2, ',', '.') }}</span>
                                        <span class="text-[11px] font-semibold text-money-700 tabular-nums">+ R$ {{ number_format($bCashback, 2, ',', '.') }} cashback</span>
                                    </div>

                                    <form action="{{ route('bundles.add-to-cart', $homeBundle->slug) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-paper transition">
                                            <x-icon name="shopping-cart" class="size-3.5" />
                                            <span>Adicionar kit</span>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($activeCoupons->isNotEmpty())
                <section x-data="{ copiedCode: '' }" class="bg-surface rounded-2xl border border-line p-5 sm:p-6 space-y-4 shadow-sm">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <x-eyebrow>Economia extra</x-eyebrow>
                            <h2 class="font-serif text-2xl text-ink mt-0.5 flex items-center gap-2">
                                <x-icon name="tag" class="size-5 text-accent-600" />
                                <span>Cupons ativos para seu carrinho</span>
                            </h2>
                        </div>
                        <span class="sr-only" aria-live="polite" x-text="copiedCode ? 'Cupom ' + copiedCode + ' copiado para a área de transferência' : ''"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5">
                        @foreach($activeCoupons as $coupon)
                            <div class="rounded-xl border border-line bg-paper p-4 flex flex-col justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded bg-ink text-paper font-mono text-xs font-bold tracking-wider">
                                            {{ $coupon->code }}
                                        </span>
                                        <span class="text-xs font-semibold text-money-700 tabular-nums">
                                            {{ $coupon->type === \App\Models\Coupon::TYPE_PERCENT ? rtrim(rtrim(number_format((float) $coupon->value, 2, ',', '.'), '0'), ',').'% OFF' : 'R$ '.number_format((float) $coupon->value, 0, ',', '.').' OFF' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-700 leading-snug pt-1">{{ $coupon->label }}</p>
                                    @if((float) $coupon->min_total > 0)
                                        <p class="text-[11px] text-slate-500 tabular-nums">
                                            Pedido mínimo: R$ {{ number_format((float) $coupon->min_total, 2, ',', '.') }}
                                        </p>
                                    @endif
                                </div>

                                <button type="button"
                                        @click="navigator.clipboard?.writeText(@js($coupon->code)); copiedCode = @js($coupon->code); setTimeout(() => { if (copiedCode === @js($coupon->code)) copiedCode = ''; }, 2500)"
                                        class="w-full py-1.5 px-3 rounded-lg border border-line bg-surface hover:border-ink text-xs font-medium text-ink flex items-center justify-center gap-1.5 transition">
                                    <x-icon name="copy" class="size-3.5" x-show="copiedCode !== @js($coupon->code)" />
                                    <x-icon name="check" class="size-3.5 text-money-600" x-show="copiedCode === @js($coupon->code)" x-cloak />
                                    <span x-text="copiedCode === @js($coupon->code) ? 'Código copiado!' : 'Copiar cupom'">Copiar cupom</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($recentlyViewedProducts->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <x-eyebrow>Histórico recente</x-eyebrow>
                            <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5 flex items-center gap-2">
                                <x-icon name="history" class="size-6 text-accent-600" />
                                <span>Vistos recentemente por você</span>
                            </h2>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-5">
                        @foreach($recentlyViewedProducts as $recentProduct)
                            <x-product-card :product="$recentProduct" :wishlistIds="$wishlistIds" />
                        @endforeach
                    </div>
                </section>
            @endif
        @endif

        <section id="catalogo" class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] xl:grid-cols-[280px_minmax(0,1fr)] gap-6 xl:gap-7 items-start">
            <aside class="hidden lg:block lg:sticky lg:top-28 space-y-5 bg-surface rounded-xl border border-line p-5 shadow-sm">
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-xl text-ink">Departamentos</h2>
                        @if($selectedCategory)
                            <a href="{{ route('catalog.index') }}" class="text-xs text-accent-600 hover:underline">Ver todos</a>
                        @endif
                    </div>

                    <ul class="space-y-1.5 text-xs">
                        <li>
                            <a href="{{ route('catalog.index') }}"
                               class="flex items-center justify-between px-2.5 py-1.5 rounded-lg transition-colors {{ ! $selectedCategory ? 'bg-ink text-paper font-medium' : 'text-slate-700 hover:bg-paper hover:text-ink' }}">
                                <span>Todos os departamentos</span>
                            </a>
                        </li>
                        @foreach($categories as $cat)
                            <li class="pt-1 border-t border-line">
                                <a href="{{ route('catalog.category', $cat->slug) }}"
                                   class="flex items-center gap-2 px-2 py-1.5 rounded-lg transition-colors {{ $selectedCategory?->id === $cat->id ? 'bg-accent-50 text-accent-600 font-semibold' : 'font-medium text-ink hover:bg-paper' }}">
                                    <x-icon :name="$cat->icon ?: 'package'" class="size-3.5 text-slate-500 shrink-0" />
                                    <span class="truncate">{{ $cat->name }}</span>
                                </a>
                                @if($cat->children->isNotEmpty())
                                    <ul class="pl-6 pr-1 pb-1 space-y-0.5">
                                        @foreach($cat->children as $child)
                                            <li>
                                                <a href="{{ route('catalog.category', $child->slug) }}"
                                                   class="block py-1 px-2 rounded-md transition-colors {{ $selectedCategory?->id === $child->id ? 'font-semibold text-accent-600 bg-accent-50' : 'text-slate-600 hover:text-ink hover:bg-paper' }}">
                                                    {{ $child->name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <form action="{{ $selectedCategory ? route('catalog.category', $selectedCategory->slug) : route('catalog.index') }}" method="GET" class="space-y-3.5 pt-4 border-t border-line text-xs">
                    @if(request('q'))
                        <input type="hidden" name="q" value="{{ request('q') }}">
                    @endif
                    @if(request('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif

                    <div class="space-y-1.5">
                        <span class="font-medium text-ink block">Faixa de preço (R$)</span>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Mín." min="0" step="1"
                                   class="w-full rounded-lg border-line bg-paper text-xs py-1.5 px-2.5 tabular-nums focus:border-ink focus:ring-ink">
                            <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Máx." min="0" step="1"
                                   class="w-full rounded-lg border-line bg-paper text-xs py-1.5 px-2.5 tabular-nums focus:border-ink focus:ring-ink">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="sidebar_min_rating" class="font-medium text-ink block">Avaliação mínima</label>
                        <select id="sidebar_min_rating" name="min_rating"
                                class="w-full rounded-lg border-line bg-paper text-xs py-1.5 px-2.5 focus:border-ink focus:ring-ink">
                            <option value="" {{ ! request('min_rating') ? 'selected' : '' }}>Todas as avaliações</option>
                            <option value="4" {{ request('min_rating') == '4' ? 'selected' : '' }}>4+ estrelas</option>
                            <option value="3" {{ request('min_rating') == '3' ? 'selected' : '' }}>3+ estrelas</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer text-slate-700 py-0.5">
                        <input type="checkbox" name="cashback" value="1" {{ request()->boolean('cashback') ? 'checked' : '' }}
                               class="rounded border-line text-ink focus:ring-ink">
                        <span>Com cashback na entrega</span>
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-ink hover:bg-slate-800 active:scale-[0.98] text-paper font-medium py-2 px-3 rounded-lg transition-all">
                            Aplicar filtros
                        </button>
                        @if(request()->hasAny(['q', 'min_price', 'max_price', 'sort', 'cashback', 'category', 'min_rating']) || $selectedCategory)
                            <a href="{{ route('catalog.index') }}" class="px-3 py-2 rounded-lg border border-line bg-surface text-slate-700 hover:border-ink transition-colors">
                                Limpar
                            </a>
                        @endif
                    </div>
                </form>

                <div class="pt-4 border-t border-line space-y-2 text-xs text-slate-600">
                    <div class="flex items-center gap-2 text-ink font-medium">
                        <x-icon name="truck" class="size-4 text-money-600 shrink-0" />
                        <span>Frete grátis acima de R$ 299</span>
                    </div>
                    <p class="leading-relaxed text-slate-500">
                        Pagamento instantâneo com saldo DigitalCard e estorno integral em caso de cancelamento.
                    </p>
                </div>
            </aside>

            <div class="min-w-0 space-y-4">
                <div class="bg-surface rounded-xl border border-line p-4 flex flex-col gap-3 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-baseline gap-3">
                            <h2 class="font-serif text-2xl sm:text-3xl text-ink">
                                {{ $selectedCategory ? $selectedCategory->name : (request('q') ? 'Resultados para “' . request('q') . '”' : 'Catálogo') }}
                            </h2>
                            <span class="text-xs text-slate-500 tabular-nums">
                                ({{ $products->total() }} {{ $products->total() === 1 ? 'produto encontrado' : 'produtos encontrados' }})
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button @click="mobileFiltersOpen = true" type="button"
                                    class="lg:hidden inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-line bg-paper text-xs font-medium text-ink">
                                <x-icon name="sliders-horizontal" class="size-3.5" />
                                <span>Filtrar</span>
                            </button>

                            <form action="{{ $selectedCategory ? route('catalog.category', $selectedCategory->slug) : route('catalog.index') }}" method="GET" class="flex items-center gap-2">
                                @foreach(request()->except(['sort', 'page']) as $k => $v)
                                    @if(is_scalar($v))
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach
                                <label for="sort-select" class="text-xs text-slate-500 hidden sm:inline">Ordenar:</label>
                                <select id="sort-select" name="sort" onchange="this.form.submit()"
                                        class="rounded-lg border-line bg-paper text-xs text-ink py-1.5 pl-2.5 pr-7 focus:border-ink focus:ring-ink">
                                    <option value="" {{ ! request('sort') ? 'selected' : '' }}>Relevância</option>
                                    <option value="menor_preco" {{ request('sort') === 'menor_preco' ? 'selected' : '' }}>Menor preço</option>
                                    <option value="maior_preco" {{ request('sort') === 'maior_preco' ? 'selected' : '' }}>Maior preço</option>
                                    <option value="maior_desconto" {{ request('sort') === 'maior_desconto' ? 'selected' : '' }}>Maior desconto</option>
                                    <option value="maior_cashback" {{ request('sort') === 'maior_cashback' ? 'selected' : '' }}>Maior cashback</option>
                                    <option value="melhor_avaliados" {{ request('sort') === 'melhor_avaliados' ? 'selected' : '' }}>Melhor avaliados</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-2 border-t border-line text-xs">
                        <a href="{{ route('catalog.index') }}"
                           class="px-3 py-1 rounded-full border transition-colors shrink-0 {{ ! $selectedCategory && ! request('sort') && ! request('cashback') ? 'bg-ink text-paper border-ink font-medium' : 'bg-paper text-slate-700 border-line hover:border-ink' }}">
                            Todos
                        </a>
                        <a href="{{ route('catalog.index', ['sort' => 'maior_desconto']) }}"
                           class="px-3 py-1 rounded-full border transition-colors shrink-0 {{ request('sort') === 'maior_desconto' ? 'bg-accent-600 text-paper border-accent-600 font-medium' : 'bg-paper text-slate-700 border-line hover:border-ink' }}">
                            Maiores descontos
                        </a>
                        <a href="{{ route('catalog.index', ['sort' => 'maior_cashback']) }}"
                           class="px-3 py-1 rounded-full border transition-colors shrink-0 {{ request('sort') === 'maior_cashback' ? 'bg-money-600 text-paper border-money-600 font-medium' : 'bg-paper text-money-700 border-money-200 hover:border-money-600' }}">
                            Maior cashback
                        </a>
                        <a href="{{ route('catalog.index', ['sort' => 'melhor_avaliados']) }}"
                           class="px-3 py-1 rounded-full border transition-colors shrink-0 {{ request('sort') === 'melhor_avaliados' ? 'bg-ink text-paper border-ink font-medium' : 'bg-paper text-slate-700 border-line hover:border-ink' }}">
                            Melhor avaliados
                        </a>
                        <a href="{{ route('catalog.index', ['cashback' => 1]) }}"
                           class="px-3 py-1 rounded-full border transition-colors shrink-0 {{ request()->boolean('cashback') ? 'bg-money-600 text-paper border-money-600 font-medium' : 'bg-paper text-money-700 border-money-200 hover:border-money-600' }}">
                            Com cashback
                        </a>
                        @foreach($categories as $quickCat)
                            <a href="{{ route('catalog.category', $quickCat->slug) }}"
                               class="px-3 py-1 rounded-full border transition-colors shrink-0 {{ $selectedCategory?->id === $quickCat->id ? 'bg-ink text-paper border-ink font-medium' : 'bg-paper text-slate-700 border-line hover:border-ink' }}">
                                {{ $quickCat->name }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @if($products->isEmpty())
                    <div class="bg-surface rounded-xl border border-line p-12 text-center space-y-3">
                        <h3 class="font-serif text-xl text-ink">Nenhum produto encontrado</h3>
                        <p class="text-xs text-slate-500">Tente remover alguns filtros ou buscar outro termo.</p>
                        <a href="{{ route('catalog.index') }}" class="inline-block bg-ink text-paper text-xs font-medium px-4 py-2 rounded-lg">
                            Limpar filtros
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 xl:gap-5">
                        @foreach($products as $product)
                            <x-product-card :product="$product" :wishlistIds="$wishlistIds" :eager="! $isHomeStorefront && $loop->index < 4" />
                        @endforeach
                    </div>

                    <div class="pt-3">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </section>

        <div x-show="mobileFiltersOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
            <div @click="mobileFiltersOpen = false" class="fixed inset-0 bg-ink/60"></div>
            <div class="fixed inset-x-0 bottom-0 max-h-[82vh] overflow-y-auto rounded-t-xl bg-surface border-t border-line p-5 shadow-float space-y-5 z-10">
                <div class="flex items-center justify-between pb-3 border-b border-line">
                    <h3 class="font-serif text-xl text-ink">Filtrar produtos</h3>
                    <button @click="mobileFiltersOpen = false" type="button" class="text-slate-500 hover:text-ink">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>

                <form action="{{ $selectedCategory ? route('catalog.category', $selectedCategory->slug) : route('catalog.index') }}" method="GET" class="space-y-4 text-xs">
                    @if(request('q'))
                        <input type="hidden" name="q" value="{{ request('q') }}">
                    @endif
                    <div class="space-y-1.5">
                        <label class="font-medium text-ink block">Departamento</label>
                        <select name="category" class="w-full rounded-md border-line bg-paper text-xs py-2 px-3">
                            <option value="">Todos</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->slug }}" {{ ($selectedCategory?->slug ?? request('category')) === $cat->slug ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                                @foreach($cat->children as $child)
                                    <option value="{{ $child->slug }}" {{ ($selectedCategory?->slug ?? request('category')) === $child->slug ? 'selected' : '' }}>
                                        — {{ $child->name }}
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="font-medium text-ink block mb-1">Preço mín. (R$)</label>
                            <input type="number" name="min_price" value="{{ request('min_price') }}" min="0"
                                   class="w-full rounded-md border-line bg-paper text-xs py-2 px-2.5 tabular-nums">
                        </div>
                        <div>
                            <label class="font-medium text-ink block mb-1">Preço máx. (R$)</label>
                            <input type="number" name="max_price" value="{{ request('max_price') }}" min="0"
                                   class="w-full rounded-md border-line bg-paper text-xs py-2 px-2.5 tabular-nums">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="mobile_min_rating" class="font-medium text-ink block">Avaliação mínima</label>
                        <select id="mobile_min_rating" name="min_rating" class="w-full rounded-md border-line bg-paper text-xs py-2 px-3">
                            <option value="" {{ ! request('min_rating') ? 'selected' : '' }}>Todas as avaliações</option>
                            <option value="4" {{ request('min_rating') == '4' ? 'selected' : '' }}>4+ estrelas</option>
                            <option value="3" {{ request('min_rating') == '3' ? 'selected' : '' }}>3+ estrelas</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-slate-700">
                        <input type="checkbox" name="cashback" value="1" {{ request()->boolean('cashback') ? 'checked' : '' }}
                               class="rounded border-line text-ink focus:ring-ink">
                        <span>Apenas com cashback</span>
                    </label>

                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="flex-1 bg-ink text-paper font-medium py-2.5 rounded-md">
                            Ver resultados
                        </button>
                        <a href="{{ route('catalog.index') }}" class="px-4 py-2.5 rounded-md border border-line bg-surface text-ink font-medium">
                            Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
