<x-app-layout :metaTitle="$product->name" :metaDescription="Str::limit($product->description, 155)" :metaImage="$product->image_url">

    @push('head')
        @php
            $galleryUrls = array_values(array_unique(array_column($product->gallerySlides(), 'url')));
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $product->description,
                'sku' => $product->sku ?: ('NX-'.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT)),
                'image' => $galleryUrls !== [] ? $galleryUrls : [$product->image_url],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => url()->current(),
                    'priceCurrency' => 'BRL',
                    'price' => number_format((float) $product->price, 2, '.', ''),
                    'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    @php
        $company = auth()->check() ? auth()->user()->primaryCompany() : null;
        $wallet = $company?->wallet;
        $balance = $wallet ? (float) $wallet->balance : 0.00;
        $productPrice = (float) $product->price;
        $hasBalance = $balance >= $productPrice;
        $walletIsActive = $wallet ? (bool) $wallet->is_active : false;
        $isWishlisted = in_array($product->id, $wishlistIds ?? [], true);
        $reviewsCount = (int) $product->reviews_count;
        $ratingValue = (float) $product->rating;
        $freeShippingMin = (float) config('nexosupri.free_shipping_min_total', 500.00);
    @endphp

    <div x-data="{
            qty: 1,
            expressCheckoutOpen: false,
            unitPrice: {{ $productPrice }},
            userBalance: {{ $balance }},
            walletActive: {{ $walletIsActive ? 'true' : 'false' }},
            get expressTotal() { return this.qty * this.unitPrice; },
            get expressCanBuy() { return this.walletActive && this.userBalance >= this.expressTotal; }
         }"
         class="app-shell py-6 pb-24 lg:pb-12 space-y-10">
        <nav aria-label="Breadcrumb" class="flex items-center flex-wrap gap-1.5 text-xs text-slate-500">
            <a href="{{ route('catalog.index') }}" class="hover:text-ink">Início</a>
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
                <div class="relative">
                    <x-product-carousel :product="$product" mode="detail" :priority="true" />

                    <form action="{{ route('wishlist.toggle', $product) }}" method="POST" class="absolute top-3 right-3 z-20">
                        @csrf
                        <button type="submit"
                                aria-label="{{ $isWishlisted ? 'Remover dos itens homologados' : 'Salvar como item homologado' }}"
                                class="h-9 w-9 rounded-full bg-surface border border-line hover:border-ink flex items-center justify-center transition shadow-sm {{ $isWishlisted ? 'text-danger-600' : 'text-slate-600 hover:text-ink' }}">
                            <x-icon name="heart" class="size-4" />
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-6 space-y-6">
                <div class="space-y-3 border-b border-line pb-5">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($product->category)
                            <x-eyebrow>{{ $product->category->name }}</x-eyebrow>
                        @endif
                        @if($product->sku)
                            <span class="font-mono text-[11px] px-2 py-0.5 rounded bg-paper border border-line text-slate-600">SKU {{ $product->sku }}</span>
                        @endif
                    </div>

                    <h1 class="font-serif text-2xl sm:text-3xl lg:text-4xl text-ink leading-tight">
                        {{ $product->name }}
                    </h1>

                    <p class="text-xs text-slate-600">
                        Apresentação: <strong class="text-ink">{{ $product->presentation ?: $product->unit }}</strong>
                        @if($product->brand)
                            · Marca: <strong class="text-ink">{{ $product->brand }}</strong>
                        @endif
                    </p>

                    <div class="flex items-center gap-3 text-xs text-slate-600">
                        @if($reviewsCount > 0)
                            <a href="#avaliacoes" class="inline-flex items-center gap-1 text-ink font-medium hover:underline">
                                <x-icon name="star" class="size-3.5" />
                                <span class="tabular-nums">{{ number_format($ratingValue, 1, ',', '.') }}</span>
                                <span class="text-slate-500 tabular-nums">({{ $reviewsCount }})</span>
                            </a>
                        @endif
                        @if(($soldUnitsCount ?? 0) > 0)
                            <span class="tabular-nums">{{ $soldUnitsCount }} {{ $soldUnitsCount === 1 ? 'unidade fornecida' : 'unidades fornecidas' }}</span>
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
                        @endif
                    </div>

                    <p class="text-xs font-mono text-money-700 font-semibold tabular-nums">
                        Equivalente a {{ number_format($productPrice, 2, ',', '.') }} créditos NexoSupri (1 crédito = R$ 1,00)
                    </p>
                </div>

                <p class="text-sm text-slate-700 leading-relaxed">
                    {{ $product->description }}
                </p>

                <div class="sticky top-24 bg-surface rounded-lg border border-line p-5 space-y-4">
                    <div class="flex items-center justify-between text-xs pb-3 border-b border-line">
                        <span class="font-medium {{ $product->stock > 0 ? 'text-money-600' : 'text-danger-600' }}">
                            {{ $product->stock > 0 ? 'Disponível para despacho' : 'Sem estoque' }}
                        </span>
                        <span class="text-slate-500 tabular-nums">{{ (int) $product->stock }} em estoque</span>
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
                                Frete grátis (pedidos acima de R$ {{ number_format($freeShippingMin, 0, ',', '.') }})
                            </p>
                        @endif
                    </div>

                    @auth
                        @if($company && $wallet)
                            <div class="flex items-center justify-between text-xs pb-3 border-b border-line">
                                <span class="text-slate-600">Saldo ({{ $company->trade_name }}):</span>
                                <span class="font-mono font-semibold tabular-nums {{ $hasBalance ? 'text-money-600' : 'text-danger-600' }}">
                                    {{ number_format($balance, 2, ',', '.') }} créd.
                                </span>
                            </div>
                        @endif
                    @endauth

                    <div class="flex items-center gap-3">
                        <label for="buy_qty_select" class="text-xs font-medium text-slate-700">Quantidade</label>
                        <select id="buy_qty_select" x-model.number="qty" class="rounded-md border-line bg-paper text-xs font-medium text-ink py-2 pl-3 pr-8 tabular-nums">
                            @for($q = 1; $q <= min(20, max(1, $product->stock)); $q++)
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
                                <span>Adicionar ao pedido</span>
                            </button>
                        </form>

                        @auth
                            @if($company)
                                <button @click="expressCheckoutOpen = !expressCheckoutOpen" type="button"
                                        class="w-full border border-line bg-surface hover:border-ink text-ink font-medium text-xs py-3 px-4 rounded-md transition">
                                    Pedido rápido
                                </button>
                            @else
                                <a href="{{ route('cart.index') }}"
                                   class="w-full border border-line bg-surface hover:border-ink text-ink font-medium text-xs py-3 px-4 rounded-md transition text-center">
                                    Ver carrinho
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}"
                               class="w-full border border-line bg-surface hover:border-ink text-ink font-medium text-xs py-3 px-4 rounded-md transition text-center">
                                Entrar para pedir
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
                        @if($company)
                            <div x-show="expressCheckoutOpen" x-cloak x-transition class="pt-3 border-t border-line space-y-3">
                                <form action="{{ route('orders.checkout') }}" method="POST" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ $checkoutToken ?? session('checkout_token') }}">
                                    <input type="hidden" name="items[0][product_id]" value="{{ $product->id }}">
                                    <input type="hidden" name="items[0][quantity]" :value="qty">

                                    <div>
                                        <label for="express_shipping_cep" class="block text-xs font-medium text-slate-700 mb-1">CEP de entrega</label>
                                        <input id="express_shipping_cep" type="text" name="shipping_cep" value="{{ old('shipping_cep', $savedCep ?? '') }}"
                                               placeholder="00000-000"
                                               class="w-full rounded-md border-line bg-paper text-xs font-mono tabular-nums py-2">
                                    </div>

                                    <div>
                                        <label for="express_shipping_address" class="block text-xs font-medium text-slate-700 mb-1">Endereço de entrega da empresa</label>
                                        <input id="express_shipping_address" type="text" name="shipping_address" value="{{ old('shipping_address', $savedAddress ?? $company->billing_address ?? '') }}" required
                                               placeholder="Rua, número, bairro, cidade - UF"
                                               class="w-full rounded-md border-line bg-paper text-xs py-2">
                                    </div>

                                    <div x-show="!walletActive" x-cloak class="rounded-md border border-danger-200 bg-danger-50 p-2.5 text-xs text-danger-700">
                                        A carteira de créditos está bloqueada. <a href="{{ route('wallet.show') }}" class="underline font-semibold">Gerenciar carteira</a>.
                                    </div>
                                    <div x-show="walletActive && userBalance < expressTotal" x-cloak class="rounded-md border border-danger-200 bg-danger-50 p-2.5 text-xs text-danger-700">
                                        Saldo insuficiente para este pedido. <a href="{{ route('wallet.show') }}" class="underline font-semibold">Contratar créditos</a>.
                                    </div>

                                    <button type="submit"
                                            :disabled="!expressCanBuy"
                                            class="w-full bg-ink hover:bg-slate-800 disabled:bg-slate-200 disabled:text-slate-500 disabled:cursor-not-allowed text-paper font-medium text-xs py-2.5 px-4 rounded-md transition tabular-nums">
                                        Confirmar pedido em créditos
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endauth
                </div>

                <div class="bg-surface rounded-lg border border-line p-5 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="h-8 w-8 rounded-md bg-paper border border-line flex items-center justify-center shrink-0">
                            <x-icon name="bell-ring" class="size-4 text-accent-600" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-ink">Alerta de preço do insumo</h2>
                            <p class="text-xs text-slate-600">Receba notificação caso a tabela deste item atinja o valor-alvo.</p>
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
                                    <button type="submit" class="text-xs font-semibold text-danger-700 hover:underline">Remover alerta</button>
                                </form>
                            </div>
                        @endif

                        @php($suggestedTarget = number_format(max(1.0, round($productPrice * 0.9, 2)), 2, '.', ''))
                        <form action="{{ route('price-alerts.store', $product) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-2.5 text-xs">
                            @csrf
                            <div class="flex-1">
                                <label for="target_price_input" class="block font-medium text-slate-700 mb-1">Valor-alvo (R$)</label>
                                <input id="target_price_input" type="number" name="target_price" step="0.01" min="1.00" max="999999.99" inputmode="decimal" required
                                       value="{{ old('target_price', $userPriceAlert?->target_price ?? $suggestedTarget) }}"
                                       class="w-full rounded-md border-line bg-paper text-xs font-mono tabular-nums py-2 px-3">
                            </div>
                            <button type="submit" class="rounded-md bg-ink hover:bg-slate-800 text-paper font-semibold px-4 py-2 transition whitespace-nowrap">
                                {{ isset($userPriceAlert) && $userPriceAlert ? 'Atualizar alerta' : 'Criar alerta' }}
                            </button>
                        </form>
                    @else
                        <div class="rounded-md border border-line bg-paper p-3 flex items-center justify-between gap-3 text-xs">
                            <span class="text-slate-600">Entre para monitorar preços deste insumo.</span>
                            <a href="{{ route('login') }}" class="font-semibold text-ink hover:underline shrink-0">Entrar →</a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>

        @if(isset($relatedProducts) && $relatedProducts->isNotEmpty())
            <section class="space-y-4 pt-6 border-t border-line">
                <div class="flex items-end justify-between">
                    <div>
                        <x-eyebrow>Catálogo B2B</x-eyebrow>
                        <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-1">Insumos relacionados</h2>
                    </div>
                    @if($product->category)
                        <a href="{{ route('catalog.category', $product->category->slug) }}" class="text-xs font-medium text-ink hover:underline">
                            Ver categoria →
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
                <x-eyebrow>Qualidade</x-eyebrow>
                <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-1">Avaliações de empresas clientes</h2>
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
                    @else
                        <p class="text-xs text-slate-600">Ainda sem avaliações registradas após entrega.</p>
                    @endif

                    @auth
                        @if($canReview ?? false)
                            <form action="{{ route('catalog.review', $product->slug) }}" method="POST" class="pt-4 border-t border-line space-y-3">
                                @csrf
                                <h3 class="text-xs font-semibold text-ink">Avaliar insumo recebido</h3>
                                <select name="rating" class="w-full rounded-md border-line bg-paper text-xs py-2 tabular-nums">
                                    @for($star = 5; $star >= 1; $star--)
                                        <option value="{{ $star }}">{{ $star }} {{ $star === 1 ? 'estrela' : 'estrelas' }}</option>
                                    @endfor
                                </select>
                                <textarea name="comment" rows="3" maxlength="1000" required placeholder="Avalie qualidade, embalagem e prazo..."
                                          class="w-full rounded-md border-line bg-paper text-xs p-3"></textarea>
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
                                    <span class="text-xs font-semibold text-ink block">{{ $review->user->name ?? 'Empresa cliente' }}</span>
                                    @if($review->is_verified_purchase)
                                        <span class="text-xs text-money-600 inline-flex items-center gap-1">
                                            <x-icon name="check" class="size-3" />
                                            <span>Fornecimento verificado</span>
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
                        <div class="p-8 rounded-lg bg-surface border border-line text-center text-xs text-slate-500">
                            Nenhuma avaliação registrada ainda.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section id="perguntas" class="pt-6 border-t border-line space-y-6">
            <div>
                <x-eyebrow>Suporte comercial</x-eyebrow>
                <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-1 flex items-center gap-2">
                    <x-icon name="message-square" class="size-6 text-accent-600" />
                    <span>Perguntas e especificações</span>
                </h2>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div class="lg:col-span-4 bg-surface rounded-lg p-5 border border-line space-y-3 text-xs">
                    @auth
                        <form action="{{ route('questions.store', $product->slug) }}" method="POST" class="space-y-3">
                            @csrf
                            <label for="product_question_input" class="block font-medium text-slate-700">Dúvida técnica ou sobre embalagem</label>
                            <textarea id="product_question_input" name="question" rows="3" minlength="8" maxlength="500" required
                                      placeholder="Ex.: Este item acompanha fardo lacrado para armazenamento?"
                                      class="w-full rounded-md border-line bg-paper text-xs p-2.5">{{ old('question') }}</textarea>
                            <button type="submit" class="w-full rounded-md bg-ink hover:bg-slate-800 px-4 py-2.5 font-semibold text-paper transition">
                                Enviar pergunta
                            </button>
                        </form>
                    @else
                        <p class="text-slate-600">Entre na sua conta para enviar perguntas sobre este insumo.</p>
                    @endauth
                </div>

                <div class="lg:col-span-8 space-y-3">
                    @forelse($questions ?? [] as $qa)
                        <details {{ $loop->first ? 'open' : '' }} class="rounded-lg border border-line bg-surface overflow-hidden">
                            <summary class="cursor-pointer px-5 py-4 flex items-start justify-between gap-4 list-none hover:bg-paper/60 transition">
                                <div class="space-y-1">
                                    <p class="text-xs sm:text-sm font-semibold text-ink">{{ $qa->question }}</p>
                                    <span class="text-[11px] text-slate-500">{{ $qa->user->name ?? 'Empresa' }} · {{ $qa->created_at?->format('d/m/Y') }}</span>
                                </div>
                                <x-icon name="chevron-down" class="size-4 text-slate-500 shrink-0 mt-1" />
                            </summary>
                            <div class="px-5 pb-4 pt-2 border-t border-line bg-paper/50 space-y-3 text-xs">
                                @if($qa->answer)
                                    <span class="inline-flex items-center gap-1 font-semibold text-money-700">
                                        <x-icon name="check" class="size-3.5" />
                                        <span>Resposta NexoSupri ({{ $qa->answered_at?->format('d/m/Y') }})</span>
                                    </span>
                                    <p class="text-slate-700 leading-relaxed">{{ $qa->answer }}</p>
                                @else
                                    <p class="text-slate-500 italic">Aguardando resposta da equipe NexoSupri.</p>
                                @endif

                                @can('admin')
                                    <form action="{{ route('admin.questions.answer', $qa) }}" method="POST" class="flex flex-col sm:flex-row gap-2 pt-2 border-t border-line">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="answer" value="{{ $qa->answer }}" minlength="5" maxlength="2000" required
                                               placeholder="Escrever resposta oficial..."
                                               class="flex-1 rounded-md border-line bg-surface text-xs py-2 px-3">
                                        <button type="submit" class="rounded-md bg-money-600 hover:bg-money-700 px-3.5 py-2 font-semibold text-paper transition shrink-0">
                                            Publicar resposta
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </details>
                    @empty
                        <div class="p-8 rounded-lg bg-surface border border-line text-center text-xs text-slate-500">
                            Nenhuma pergunta publicada ainda.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <div class="fixed inset-x-0 bottom-0 z-30 lg:hidden bg-surface border-t border-line px-4 py-3 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="block text-[11px] text-slate-500 tabular-nums" x-text="qty + '× · Valor / Créditos'">Valor / Créditos</span>
                <span class="text-base font-semibold text-ink tabular-nums"
                      x-text="'R$ ' + expressTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })">
                    R$ {{ number_format($productPrice, 2, ',', '.') }}
                </span>
            </div>
            <form action="{{ route('cart.add', $product) }}" method="POST" class="flex-1 max-w-[220px]">
                @csrf
                <input type="hidden" name="quantity" :value="qty">
                <button type="submit" class="w-full bg-ink text-paper text-xs font-medium py-2.5 px-4 rounded-md flex items-center justify-center gap-1.5">
                    <x-icon name="shopping-cart" class="size-3.5" />
                    <span>Adicionar ao pedido</span>
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
