<x-app-layout :metaTitle="$product->name" :metaDescription="Str::limit($product->description, 155)" :metaImage="$product->image_url">

    @push('head')
        @php
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $product->description,
                'sku' => 'PROD-'.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT),
                'image' => [$product->image_url],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => url()->current(),
                    'priceCurrency' => 'BRL',
                    'price' => number_format((float) $product->price, 2, '.', ''),
                    'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ],
            ];
            if ((int) $product->reviews_count > 0) {
                $jsonLd['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => number_format((float) $product->rating, 1, '.', ''),
                    'reviewCount' => (int) $product->reviews_count,
                ];
            }
            $breadcrumbLd = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Início',
                        'item' => route('catalog.index'),
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $product->category?->name ?? 'Catálogo',
                        'item' => $product->category ? route('catalog.category', $product->category->slug) : route('catalog.index'),
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $product->name,
                        'item' => url()->current(),
                    ],
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($breadcrumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    @php
        $userCard = auth()->check() ? auth()->user()->virtualCard : null;
        $balance = $userCard ? (float) $userCard->balance : 0.00;
        $productPrice = (float) $product->price;
        $hasBalance = $balance >= $productPrice;
        $cardIsActive = $userCard ? (bool) $userCard->is_active : false;
        $hasPinConfigured = $userCard && ! empty($userCard->security_pin);
        $remainingBalance = round($balance - $productPrice, 2);
        $maxCb = (float) config('digitalcard.max_cashback_percent', 15.0);
        $bonusCb = (float) ($loyaltySummary['bonus_cashback_percent'] ?? 0.0);
        $effectiveCbPercent = min(20.0, min((float) $product->cashback_percent, $maxCb) + $bonusCb);
        $cashbackValue = round($productPrice * ($effectiveCbPercent / 100), 2);
        $isWishlisted = in_array($product->id, $wishlistIds ?? [], true);
        $reviewsCount = (int) $product->reviews_count;
        $ratingValue = (float) $product->rating;
        $freeShippingMin = (float) ($loyaltySummary['free_shipping_min'] ?? config('digitalcard.free_shipping_min_total', 299.00));
    @endphp

    <div x-data="{
            qty: 1,
            expressCheckoutOpen: false,
            unitPrice: {{ $productPrice }},
            userBalance: {{ $balance }},
            cardActive: {{ $cardIsActive ? 'true' : 'false' }},
            hasPin: {{ $hasPinConfigured ? 'true' : 'false' }},
            get expressTotal() { return this.qty * this.unitPrice; },
            get expressCanBuy() { return this.cardActive && this.hasPin && this.userBalance >= this.expressTotal; }
         }"
         class="app-shell py-6 pb-24 lg:pb-12 space-y-10">
        <nav aria-label="Breadcrumb" class="flex items-center flex-wrap gap-1.5 text-xs text-slate-500">
            <a href="{{ route('catalog.index') }}" class="hover:text-ink">Início</a>
            @if($product->category?->parent)
                <span>/</span>
                <a href="{{ route('catalog.category', $product->category->parent->slug) }}" class="hover:text-ink">
                    {{ $product->category->parent->name }}
                </a>
            @endif
            @if($product->category)
                <span>/</span>
                <a href="{{ route('catalog.category', $product->category->slug) }}" class="hover:text-ink">
                    {{ $product->category->name }}
                </a>
            @endif
            <span>/</span>
            <span class="text-ink font-medium truncate max-w-xs">{{ $product->name }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">
            <div class="lg:col-span-6 space-y-3">
                <div class="relative rounded-lg border border-line bg-surface overflow-hidden">
                    <x-product-image :product="$product" size="full" :priority="true" />

                    <form action="{{ route('wishlist.toggle', $product) }}" method="POST" class="absolute top-3 right-3">
                        @csrf
                        <button type="submit"
                                aria-label="{{ $isWishlisted ? 'Remover dos favoritos' : 'Salvar nos favoritos' }}"
                                class="h-9 w-9 rounded-full bg-surface border border-line hover:border-ink flex items-center justify-center transition {{ $isWishlisted ? 'text-danger-600' : 'text-slate-600 hover:text-ink' }}">
                            <x-icon name="heart" class="size-4" />
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-6 space-y-6">
                <div class="space-y-3 border-b border-line pb-5">
                    @if($product->category)
                        <x-eyebrow>{{ $product->category->name }}</x-eyebrow>
                    @endif

                    <h1 class="font-serif text-4xl text-ink leading-tight">
                        {{ $product->name }}
                    </h1>

                    <div class="flex items-center gap-3 text-xs text-slate-600">
                        @if($reviewsCount > 0)
                            <a href="#avaliacoes" class="inline-flex items-center gap-1 text-ink font-medium hover:underline">
                                <x-icon name="star" class="size-3.5" />
                                <span class="tabular-nums">{{ number_format($ratingValue, 1, ',', '.') }}</span>
                                <span class="text-slate-500 tabular-nums">({{ $reviewsCount }} {{ $reviewsCount === 1 ? 'avaliação' : 'avaliações' }})</span>
                            </a>
                        @else
                            <a href="#avaliacoes" class="text-slate-500 hover:text-ink hover:underline">
                                Seja o primeiro a avaliar
                            </a>
                        @endif

                        @if(($soldUnitsCount ?? 0) > 0)
                            <span>·</span>
                            <span class="tabular-nums">{{ $soldUnitsCount }} {{ $soldUnitsCount === 1 ? 'vendido' : 'vendidos' }}</span>
                        @endif
                    </div>
                </div>

                <div class="space-y-2 border-b border-line pb-5">
                    <div class="flex items-baseline flex-wrap gap-x-3 gap-y-1">
                        <span class="text-[32px] leading-none font-semibold text-ink tabular-nums">
                            R$ {{ number_format($productPrice, 2, ',', '.') }}
                        </span>

                        @if($product->discount_percent > 0)
                            <span class="text-sm text-slate-500 line-through tabular-nums">
                                R$ {{ number_format((float) $product->compare_at_price, 2, ',', '.') }}
                            </span>
                            <span class="text-sm font-medium text-accent-600 tabular-nums">
                                −{{ $product->discount_percent }}%
                            </span>
                        @endif
                    </div>

                    @if($effectiveCbPercent > 0)
                        <p class="text-xs font-medium text-money-600 tabular-nums">
                            + R$ {{ number_format($cashbackValue, 2, ',', '.') }} ({{ rtrim(rtrim(number_format($effectiveCbPercent, 2, ',', '.'), '0'), ',') }}%) de cashback ao receber o pedido
                        </p>
                    @endif
                </div>

                <p class="text-sm text-slate-700 leading-relaxed">
                    {{ $product->description }}
                </p>

                <div class="sticky top-24 bg-surface rounded-lg border border-line p-5 space-y-4">
                    <div class="flex items-center justify-between text-xs pb-3 border-b border-line">
                        <span class="font-medium {{ $product->stock > 0 ? 'text-money-600' : 'text-danger-600' }}">
                            {{ $product->stock > 0 ? 'Em estoque' : 'Indisponível' }}
                        </span>
                        <span class="text-slate-500 tabular-nums">{{ (int) $product->stock }} {{ (int) $product->stock === 1 ? 'unidade disponível' : 'unidades disponíveis' }}</span>
                    </div>

                    <div class="space-y-2 pb-3 border-b border-line text-xs">
                        <form action="{{ route('address.save') }}" method="POST" class="flex gap-2">
                            @csrf
                            <input type="text" name="cep" value="{{ $savedCep }}" required placeholder="Calcular frete (CEP)"
                                   aria-label="Calcular frete por CEP"
                                   class="flex-1 rounded-md border-line bg-paper text-xs font-mono tabular-nums py-2 px-3 focus:border-ink focus:ring-ink">
                            <button type="submit" class="px-3 py-2 rounded-md border border-line bg-surface hover:border-ink text-xs font-medium text-ink">
                                Calcular
                            </button>
                        </form>

                        @if(! empty($shippingEstimate))
                            <p class="text-xs {{ $shippingEstimate['is_free'] ? 'text-money-600 font-medium' : 'text-slate-700' }} tabular-nums">
                                {{ $shippingEstimate['is_free'] ? 'Frete grátis' : 'Frete: R$ '.number_format($shippingEstimate['cost'], 2, ',', '.') }}
                                · {{ $shippingEstimate['estimated_days'] }} dias úteis ({{ $shippingEstimate['uf'] }})
                            </p>
                        @elseif($productPrice >= $freeShippingMin)
                            <p class="text-xs text-money-600 font-medium tabular-nums">
                                Frete grátis (compras acima de R$ {{ number_format($freeShippingMin, 0, ',', '.') }})
                            </p>
                        @endif
                    </div>

                    @auth
                        <div class="flex items-center justify-between text-xs pb-3 border-b border-line">
                            <span class="text-slate-600">Saldo no cartão:</span>
                            <span class="font-mono font-semibold tabular-nums {{ $hasBalance ? 'text-money-600' : 'text-danger-600' }}">
                                R$ {{ number_format($balance, 2, ',', '.') }}
                            </span>
                        </div>
                    @endauth

                    <div class="flex items-center gap-3">
                        <label for="buy_qty_select" class="text-xs font-medium text-slate-700">Quantidade</label>
                        <select id="buy_qty_select" x-model.number="qty" class="rounded-md border-line bg-paper text-xs font-medium text-ink py-2 pl-3 pr-8 tabular-nums">
                            @for($q = 1; $q <= min(10, max(1, $product->stock)); $q++)
                                <option value="{{ $q }}">{{ $q }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <form action="{{ route('cart.add', $product) }}" method="POST">
                            @csrf
                            <input type="hidden" name="quantity" :value="qty">
                            <button type="submit"
                                    class="w-full bg-ink hover:bg-slate-800 text-paper font-medium text-xs py-3 px-4 rounded-md transition flex items-center justify-center gap-2">
                                <x-icon name="shopping-cart" class="size-4" />
                                <span>Adicionar ao carrinho</span>
                            </button>
                        </form>

                        @auth
                            <button @click="expressCheckoutOpen = !expressCheckoutOpen" type="button"
                                    class="w-full border border-line bg-surface hover:border-ink text-ink font-medium text-xs py-3 px-4 rounded-md transition">
                                Comprar agora
                            </button>
                        @else
                            <a href="{{ route('login') }}"
                               class="w-full border border-line bg-surface hover:border-ink text-ink font-medium text-xs py-3 px-4 rounded-md transition text-center">
                                Entrar para comprar
                            </a>
                        @endauth
                    </div>

                    @php
                        $inCompare = in_array($product->id, $compareIds ?? session('compare_product_ids', []), true);
                    @endphp
                    <form action="{{ route('compare.toggle', $product) }}" method="POST">
                        @csrf
                        <button type="submit"
                                aria-pressed="{{ $inCompare ? 'true' : 'false' }}"
                                class="w-full py-2 px-3 rounded-md border text-xs font-medium transition flex items-center justify-center gap-1.5 {{ $inCompare ? 'bg-ink text-paper border-ink' : 'border-line bg-paper text-ink hover:border-ink' }}">
                            <x-icon name="scale" class="size-3.5" />
                            <span>{{ $inCompare ? 'No comparador (remover)' : 'Comparar lado a lado' }}</span>
                        </button>
                    </form>

                    @auth
                        <div x-show="expressCheckoutOpen" x-cloak x-transition class="pt-3 border-t border-line space-y-3">
                            <form action="{{ route('orders.checkout') }}" method="POST" class="space-y-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ $checkoutToken ?? session('checkout_token') }}">
                                <input type="hidden" name="items[0][product_id]" value="{{ $product->id }}">
                                <input type="hidden" name="items[0][quantity]" :value="qty">

                                <div>
                                    <label for="express_shipping_cep" class="block text-xs font-medium text-slate-700 mb-1">CEP de entrega (opcional)</label>
                                    <input id="express_shipping_cep" type="text" name="shipping_cep" value="{{ old('shipping_cep', $savedCep ?? '') }}"
                                           placeholder="00000-000"
                                           class="w-full rounded-md border-line bg-paper text-xs font-mono tabular-nums py-2">
                                </div>

                                <div>
                                    <label for="express_shipping_address" class="block text-xs font-medium text-slate-700 mb-1">Endereço para entrega</label>
                                    <input id="express_shipping_address" type="text" name="shipping_address" value="{{ old('shipping_address', $savedAddress ?? '') }}" required
                                           placeholder="Rua, número, bairro, cidade - UF, CEP"
                                           class="w-full rounded-md border-line bg-paper text-xs py-2">
                                </div>

                                <div>
                                    <label for="express_card_pin" class="block text-xs font-medium text-slate-700 mb-1">PIN do cartão (4 dígitos)</label>
                                    <input id="express_card_pin" type="password" name="card_pin" inputmode="numeric" maxlength="4" required placeholder="••••"
                                           class="w-full rounded-md border-line bg-paper text-xs py-2 font-mono tabular-nums">
                                </div>

                                <div x-show="!cardActive" x-cloak class="rounded-md border border-danger-200 bg-danger-50 p-2.5 text-xs text-danger-700">
                                    Seu cartão virtual está bloqueado. <a href="{{ route('card.show') }}" class="underline font-semibold">Desbloqueie no cartão</a>.
                                </div>
                                <div x-show="cardActive && !hasPin" x-cloak class="rounded-md border border-accent-200 bg-accent-50 p-2.5 text-xs text-accent-900">
                                    Cadastre um PIN de 4 dígitos antes de comprar. <a href="{{ route('card.show') }}" class="underline font-semibold">Configurar PIN</a>.
                                </div>
                                <div x-show="cardActive && hasPin && userBalance < expressTotal" x-cloak class="rounded-md border border-danger-200 bg-danger-50 p-2.5 text-xs text-danger-700">
                                    Saldo insuficiente para a quantidade selecionada. <a href="{{ route('card.show') }}" class="underline font-semibold">Recarregar PIX</a>.
                                </div>

                                <button type="submit"
                                        :disabled="!expressCanBuy"
                                        class="w-full bg-ink hover:bg-slate-800 disabled:bg-slate-200 disabled:text-slate-500 disabled:cursor-not-allowed text-paper font-medium text-xs py-2.5 px-4 rounded-md transition tabular-nums">
                                    Confirmar compra
                                </button>
                                <p class="text-xs text-slate-500">Pagamento instantâneo com saldo DigitalCard protegido por PIN.</p>
                            </form>
                        </div>
                    @endauth
                </div>

                <div class="bg-surface rounded-lg border border-line p-5 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="h-8 w-8 rounded-md bg-paper border border-line flex items-center justify-center shrink-0">
                                <x-icon name="bell-ring" class="size-4 text-accent-600" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-ink">Alerta de queda de preço</h2>
                                <p class="text-xs text-slate-600">Receba notificação automática quando o preço atingir sua meta.</p>
                            </div>
                        </div>
                    </div>

                    @auth
                        @if(isset($userPriceAlert) && $userPriceAlert)
                            <div class="rounded-md border border-money-200 bg-money-50 p-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <span class="font-medium text-money-800 tabular-nums">
                                    Alerta ativo: avisar em R$ {{ number_format((float) $userPriceAlert->target_price, 2, ',', '.') }}
                                </span>
                                <form action="{{ route('price-alerts.destroy', $userPriceAlert) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-danger-700 hover:underline">
                                        Remover alerta
                                    </button>
                                </form>
                            </div>
                        @endif

                        @php
                            $suggestedTarget = number_format(max(1.0, round($productPrice * 0.9, 2)), 2, '.', '');
                        @endphp
                        <form action="{{ route('price-alerts.store', $product) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-2.5 text-xs">
                            @csrf
                            <div class="flex-1">
                                <label for="target_price_input" class="block font-medium text-slate-700 mb-1">
                                    Preço-alvo desejado (R$)
                                </label>
                                <input id="target_price_input" type="number" name="target_price" step="0.01" min="1.00" max="999999.99" inputmode="decimal" required
                                       value="{{ old('target_price', $userPriceAlert?->target_price ?? $suggestedTarget) }}"
                                       class="w-full rounded-md border-line bg-paper text-xs font-mono tabular-nums py-2 px-3 focus:border-ink focus:ring-ink">
                            </div>
                            <button type="submit"
                                    class="rounded-md bg-ink hover:bg-slate-800 text-paper font-semibold px-4 py-2 transition whitespace-nowrap">
                                {{ isset($userPriceAlert) && $userPriceAlert ? 'Atualizar alerta' : 'Criar alerta de preço' }}
                            </button>
                        </form>
                    @else
                        <div class="rounded-md border border-line bg-paper p-3 flex items-center justify-between gap-3 text-xs">
                            <span class="text-slate-600">Entre na sua conta para ativar alertas de preço.</span>
                            <a href="{{ route('login') }}" class="font-semibold text-ink hover:underline shrink-0">Entrar →</a>
                        </div>
                    @endauth
                </div>

                @auth
                    <div class="bg-surface rounded-lg border border-line p-4 flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="h-8 w-8 rounded-md bg-paper border border-line flex items-center justify-center shrink-0">
                                <x-icon name="piggy-bank" class="size-4 text-money-600" />
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold text-ink truncate">Planejando esta compra?</p>
                                <p class="text-slate-600">Crie uma meta vinculada no Cofrinho DigitalCard.</p>
                            </div>
                        </div>
                        <a href="{{ route('card.show', ['goal_product' => $product->id]) }}"
                           class="rounded-md border border-line bg-paper hover:border-ink px-3 py-2 font-semibold text-ink transition shrink-0">
                            Guardar saldo →
                        </a>
                    </div>
                @endauth
            </div>
        </div>

        @if(isset($relatedProducts) && $relatedProducts->isNotEmpty())
            <section class="space-y-4 pt-6 border-t border-line">
                <div class="flex items-end justify-between">
                    <div>
                        <x-eyebrow>Catálogo</x-eyebrow>
                        <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-1">Produtos relacionados</h2>
                    </div>
                    @if($product->category)
                        <a href="{{ route('catalog.category', $product->category->slug) }}" class="text-xs font-medium text-ink hover:underline">
                            Ver todos →
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-5">
                    @foreach($relatedProducts as $rel)
                        <x-product-card :product="$rel" :wishlistIds="$wishlistIds" />
                    @endforeach
                </div>
            </section>
        @endif

        <section id="avaliacoes" class="pt-6 border-t border-line space-y-6">
            <div>
                <x-eyebrow>Avaliações</x-eyebrow>
                <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-1">Avaliações de clientes</h2>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div class="lg:col-span-4 bg-surface rounded-lg p-5 border border-line space-y-4">
                    @if($reviewsCount > 0)
                        <div class="flex items-center gap-4">
                            <span class="font-serif text-4xl text-ink tabular-nums">{{ number_format($ratingValue, 1, ',', '.') }}</span>
                            <div>
                                <div class="flex items-center gap-0.5 text-ink" aria-hidden="true">
                                    @for($i = 1; $i <= 5; $i++)
                                        <x-icon name="star" class="size-4 {{ $i <= (int) round($ratingValue) ? 'text-ink' : 'text-slate-300' }}" />
                                    @endfor
                                </div>
                                <span class="text-xs text-slate-600 block mt-0.5 tabular-nums">
                                    {{ $reviewsCount }} {{ $reviewsCount === 1 ? 'avaliação verificada' : 'avaliações verificadas' }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2 text-xs pt-2 border-t border-line">
                            @foreach([5, 4, 3, 2, 1] as $stars)
                                @php
                                    $dist = $ratingDistribution[$stars] ?? ['count' => 0, 'percent' => 0];
                                @endphp
                                <div class="flex items-center gap-2">
                                    <span class="w-10 font-medium text-slate-700 tabular-nums">{{ $stars }}</span>
                                    <div class="flex-1 h-1.5 bg-line rounded-full overflow-hidden">
                                        <div class="h-full bg-ink rounded-full" style="width: {{ $dist['percent'] }}%"></div>
                                    </div>
                                    <span class="w-16 text-right font-mono tabular-nums text-slate-600">{{ $dist['percent'] }}% ({{ $dist['count'] }})</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="space-y-1.5 py-1">
                            <p class="text-sm font-medium text-ink">Ainda sem avaliações</p>
                            <p class="text-xs text-slate-600">Seja o primeiro a avaliar este produto após comprar e receber seu pedido.</p>
                        </div>
                    @endif

                    @auth
                        @if($canReview ?? false)
                            <form action="{{ route('catalog.review', $product->slug) }}" method="POST"
                                  x-data="{ selectedRating: 5 }"
                                  class="pt-4 border-t border-line space-y-3">
                                @csrf
                                <h3 class="text-xs font-semibold text-ink">Avaliar este produto</h3>

                                <div>
                                    <label class="block text-xs text-slate-700 mb-1">Nota (1 a 5):</label>
                                    <select name="rating" x-model.number="selectedRating" class="w-full rounded-md border-line bg-paper text-xs py-2 tabular-nums">
                                        @for($star = 5; $star >= 1; $star--)
                                            <option value="{{ $star }}">{{ $star }} {{ $star === 1 ? 'estrela' : 'estrelas' }}</option>
                                        @endfor
                                    </select>
                                </div>

                                <div>
                                    <label for="review_comment" class="sr-only">Comentário da avaliação</label>
                                    <textarea id="review_comment" name="comment" rows="3" maxlength="1000" required
                                              placeholder="Conte sua experiência com o produto..."
                                              class="w-full rounded-md border-line bg-paper text-xs p-3"></textarea>
                                </div>

                                <button type="submit" class="w-full bg-ink hover:bg-slate-800 text-paper font-medium text-xs py-2.5 px-4 rounded-md transition">
                                    Publicar avaliação
                                </button>
                            </form>
                        @endif
                    @endauth
                </div>

                <div class="lg:col-span-8 space-y-3">
                    @forelse(($reviews ?? $product->reviews) as $review)
                        <div class="p-4 rounded-lg bg-surface border border-line space-y-2">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <div>
                                    <span class="text-xs font-semibold text-ink block">{{ $review->user->name ?? 'Cliente' }}</span>
                                    @if($review->is_verified_purchase)
                                        <span class="text-xs text-money-600 inline-flex items-center gap-1">
                                            <x-icon name="check" class="size-3" />
                                            <span>Compra verificada</span>
                                        </span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-mono tabular-nums text-ink font-medium">Nota {{ (int) $review->rating }}/5</span>
                                    <span class="block text-xs text-slate-500 tabular-nums">{{ $review->created_at->format('d/m/Y') }}</span>
                                </div>
                            </div>
                            @if($review->comment)
                                <p class="text-xs text-slate-700 leading-relaxed pt-1">{{ $review->comment }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="p-8 rounded-lg bg-surface border border-line text-center space-y-1">
                            <p class="text-sm font-medium text-ink">Nenhuma avaliação registrada.</p>
                            <p class="text-xs text-slate-500">As avaliações aparecem aqui após a entrega do pedido.</p>
                        </div>
                    @endforelse

                    @if(isset($reviews) && method_exists($reviews, 'links'))
                        <div class="pt-2">
                            {{ $reviews->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section id="perguntas" class="pt-6 border-t border-line space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <x-eyebrow>Tira-dúvidas</x-eyebrow>
                    <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-1 flex items-center gap-2">
                        <x-icon name="message-square" class="size-6 text-accent-600" />
                        <span>Perguntas e respostas</span>
                    </h2>
                </div>
                <span class="text-xs text-slate-500 tabular-nums">
                    {{ isset($questions) ? $questions->count() : 0 }} {{ (isset($questions) ? $questions->count() : 0) === 1 ? 'pergunta publicada' : 'perguntas publicadas' }}
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div class="lg:col-span-4 bg-surface rounded-lg p-5 border border-line space-y-3 text-xs">
                    <h3 class="text-sm font-semibold text-ink">Tem alguma dúvida técnica?</h3>
                    <p class="text-slate-600 leading-relaxed">Envie sua pergunta sobre compatibilidade, dimensões ou garantia. Você receberá uma notificação quando respondermos.</p>

                    @auth
                        <form action="{{ route('questions.store', $product->slug) }}" method="POST" class="space-y-3 pt-1">
                            @csrf
                            <div>
                                <label for="product_question_input" class="block font-medium text-slate-700 mb-1">Sua pergunta</label>
                                <textarea id="product_question_input" name="question" rows="3" minlength="8" maxlength="500" required
                                          placeholder="Ex.: Este modelo é compatível com macOS e Linux sem drivers extras?"
                                          class="w-full rounded-md border-line bg-paper text-xs p-2.5 focus:border-ink focus:ring-ink">{{ old('question') }}</textarea>
                            </div>
                            <button type="submit"
                                    class="w-full rounded-md bg-ink hover:bg-slate-800 px-4 py-2.5 font-semibold text-paper transition">
                                Enviar pergunta
                            </button>
                        </form>
                    @else
                        <div class="rounded-md border border-line bg-paper p-3 flex items-center justify-between gap-2">
                            <span class="text-slate-600">Entre para perguntar sobre este produto.</span>
                            <a href="{{ route('login') }}" class="font-semibold text-ink hover:underline shrink-0">Entrar →</a>
                        </div>
                    @endauth
                </div>

                <div class="lg:col-span-8 space-y-3">
                    @forelse($questions ?? [] as $qa)
                        <details name="product-qa" {{ $loop->first ? 'open' : '' }} class="group rounded-lg border border-line bg-surface overflow-hidden">
                            <summary class="cursor-pointer px-5 py-4 flex items-start justify-between gap-4 list-none hover:bg-paper/60 transition">
                                <div class="space-y-1 min-w-0">
                                    <p class="text-xs sm:text-sm font-semibold text-ink leading-snug">{{ $qa->question }}</p>
                                    <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                                        <span>Por {{ $qa->user->name ?? 'Cliente' }}</span>
                                        <span>·</span>
                                        <span class="tabular-nums">{{ $qa->created_at?->format('d/m/Y') }}</span>
                                        <span>·</span>
                                        <span class="font-medium {{ $qa->answer ? 'text-money-700' : 'text-accent-700' }}">
                                            {{ $qa->answer ? 'Respondida' : 'Aguardando resposta' }}
                                        </span>
                                    </div>
                                </div>
                                <x-icon name="chevron-down" class="size-4 text-slate-500 shrink-0 mt-1 group-open:rotate-180 transition-transform" />
                            </summary>

                            <div class="px-5 pb-4 pt-2 border-t border-line bg-paper/50 space-y-3 text-xs">
                                @if($qa->answer)
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center gap-1 font-semibold text-money-700">
                                            <x-icon name="check" class="size-3.5" />
                                            <span>Resposta DigitalCard ({{ $qa->answered_at?->format('d/m/Y') }})</span>
                                        </span>
                                        <p class="text-slate-700 leading-relaxed">{{ $qa->answer }}</p>
                                    </div>
                                @else
                                    <p class="text-slate-500 italic">Ainda sem resposta publicada pela equipe técnica.</p>
                                @endif

                                @can('admin')
                                    <form action="{{ route('admin.questions.answer', $qa) }}" method="POST" class="flex flex-col sm:flex-row gap-2 pt-2 border-t border-line">
                                        @csrf
                                        @method('PATCH')
                                        <label for="qa_answer_{{ $qa->id }}" class="sr-only">Responder pergunta #{{ $qa->id }}</label>
                                        <input id="qa_answer_{{ $qa->id }}" type="text" name="answer" value="{{ $qa->answer }}" minlength="5" maxlength="2000" required
                                               placeholder="Escrever resposta oficial..."
                                               class="flex-1 rounded-md border-line bg-surface text-xs py-2 px-3">
                                        <button type="submit" class="rounded-md bg-money-600 hover:bg-money-700 px-3.5 py-2 font-semibold text-paper transition shrink-0">
                                            {{ $qa->answer ? 'Atualizar resposta' : 'Publicar resposta' }}
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </details>
                    @empty
                        <div class="p-8 rounded-lg bg-surface border border-line text-center space-y-1">
                            <p class="text-sm font-medium text-ink">Nenhuma pergunta publicada ainda.</p>
                            <p class="text-xs text-slate-500">Seja o primeiro a enviar uma dúvida técnica sobre este item.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        @if(isset($recentlyViewedProducts) && $recentlyViewedProducts->isNotEmpty())
            <section class="space-y-4 pt-6 border-t border-line">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <x-eyebrow>Histórico recente</x-eyebrow>
                        <h2 class="font-serif text-2xl text-ink mt-1 flex items-center gap-2">
                            <x-icon name="history" class="size-5 text-accent-600" />
                            <span>Vistos recentemente</span>
                        </h2>
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-5">
                    @foreach($recentlyViewedProducts as $recentItem)
                        <x-product-card :product="$recentItem" :wishlistIds="$wishlistIds" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 lg:hidden bg-surface border-t border-line px-4 py-3 flex items-center justify-between gap-3">
        <div>
            <span class="block text-xs text-slate-500">Preço</span>
            <span class="text-base font-semibold text-ink tabular-nums">R$ {{ number_format($productPrice, 2, ',', '.') }}</span>
        </div>
        <form action="{{ route('cart.add', $product) }}" method="POST" class="flex-1 max-w-[220px]">
            @csrf
            <input type="hidden" name="quantity" value="1">
            <button type="submit" class="w-full bg-ink text-paper text-xs font-medium py-2.5 px-4 rounded-md flex items-center justify-center gap-1.5">
                <x-icon name="shopping-cart" class="size-3.5" />
                <span>Adicionar ao carrinho</span>
            </button>
        </form>
    </div>
</x-app-layout>
