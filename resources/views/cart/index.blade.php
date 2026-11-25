<x-app-layout :metaTitle="'Pedido em Montagem'" :metaDescription="'Revise os insumos selecionados e confirme seu pedido utilizando os créditos da empresa na NexoSupri.'">
    @php
        $defaultAddr = auth()->check() ? auth()->user()->addresses()->where('is_default', true)->first() : null;
        $savedAddress = session('shipping_address') ?: ($defaultAddr ? $defaultAddr->formattedLine() : ($company?->billing_address ?? ''));
        $savedCep = session('shipping_cep') ?: ($defaultAddr?->cep ?? $company?->billing_cep ?? '');
        $balance = (float) ($wallet->balance ?? 0);
        $hasBalance = $wallet && $balance >= $total;

        $freeShippingMin = (float) config('nexosupri.free_shipping_min_total', 500.00);
        $merchandiseNet = max(0.0, round(($subtotalAll ?? 0) - ($discountAmount ?? 0), 2));
        $missingForFreeShipping = $freeShippingMin <= 0 ? 0.0 : max(0.0, round($freeShippingMin - $merchandiseNet, 2));
        $freeShippingProgress = $freeShippingMin <= 0 ? 100 : min(100, (int) round(($merchandiseNet / $freeShippingMin) * 100));
    @endphp

    <div class="app-shell py-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 pb-5 border-b border-line">
            <div>
                <p class="text-xs font-medium text-slate-500">1 Seleção de insumos — 2 Entrega — 3 Débito / Aprovação em créditos</p>
                <h1 class="font-serif text-3xl sm:text-4xl text-ink mt-1">Pedido de Abastecimento</h1>
            </div>

            <a href="{{ route('catalog.index') }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-line bg-surface hover:border-ink px-4 py-2 text-xs font-medium text-ink transition">
                <x-icon name="arrow-left" class="size-3.5" />
                <span>Continuar selecionando</span>
            </a>
        </div>

        @if(! empty($priceWarnings))
            <div role="alert" class="rounded-lg border border-line bg-surface p-4 text-xs text-ink space-y-1">
                <p class="font-semibold">Aviso de atualização de preço:</p>
                @foreach($priceWarnings as $warn)
                    <p>{{ $warn }}</p>
                @endforeach
            </div>
        @endif

        @if (empty($items))
            <div class="rounded-lg border border-line bg-surface p-12 text-center space-y-3">
                <h2 class="font-serif text-2xl text-ink">Seu pedido está vazio</h2>
                <p class="mx-auto max-w-sm text-xs text-slate-600">Explore as categorias da NexoSupri e adicione os insumos recorrentes da empresa.</p>
                <a href="{{ route('catalog.index') }}"
                   class="mt-2 inline-block rounded-md bg-ink hover:bg-slate-800 px-5 py-2.5 text-xs font-medium text-paper transition">
                    Ver catálogo
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 items-start">
                <div class="lg:col-span-8 space-y-6">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-4 text-xs">
                            @if($missingForFreeShipping <= 0)
                                <p class="font-medium text-money-600 inline-flex items-center gap-1.5">
                                    <x-icon name="check" class="size-3.5" />
                                    <span>Frete grátis liberado para este pedido corporativo</span>
                                </p>
                            @else
                                <p class="text-slate-700">
                                    Faltam <strong class="font-mono tabular-nums text-ink">R$ {{ number_format($missingForFreeShipping, 2, ',', '.') }}</strong> para frete grátis
                                </p>
                            @endif
                            <span class="font-mono tabular-nums text-xs text-slate-500">{{ $freeShippingProgress }}%</span>
                        </div>
                        <div class="w-full h-1 bg-line rounded-full overflow-hidden">
                            <div class="h-full bg-money-600 transition-all duration-300" style="width: {{ $freeShippingProgress }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-line bg-surface overflow-hidden">
                        <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                            <h2 class="text-xs font-medium text-slate-600 tabular-nums">
                                {{ count($items) }} {{ count($items) === 1 ? 'insumo selecionado' : 'insumos selecionados' }}
                            </h2>
                            <form action="{{ route('cart.clear') }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-danger-600 hover:underline">
                                    Limpar lista
                                </button>
                            </form>
                        </div>

                        <div class="divide-y divide-line">
                            @foreach ($items as $item)
                                @php($product = $item['product'])
                                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center justify-between">
                                    <div class="flex items-center gap-4 flex-1 min-w-0">
                                        <a href="{{ route('catalog.product', $product->slug) }}" class="w-20 shrink-0 rounded-md overflow-hidden border border-line">
                                            <x-product-image :product="$product" size="thumb" />
                                        </a>

                                        <div class="min-w-0 flex-1 space-y-1">
                                            <span class="text-xs text-slate-500 block">{{ $product->category->name ?? 'Catálogo' }} · {{ $product->presentation ?: $product->unit }}</span>
                                            <a href="{{ route('catalog.product', $product->slug) }}"
                                               class="block text-sm font-medium leading-snug text-ink hover:underline line-clamp-2">
                                                {{ $product->name }}
                                            </a>
                                            <div class="flex flex-wrap items-center gap-3 pt-0.5 text-xs">
                                                <span class="text-slate-600 tabular-nums">
                                                    Unitário: <strong class="font-mono text-ink">R$ {{ number_format((float) $product->price, 2, ',', '.') }}</strong>
                                                </span>
                                                <span class="font-mono text-[11px] text-slate-500">
                                                    {{ number_format($item['subtotal'], 2, ',', '.') }} créditos
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-5 pt-2 sm:pt-0 border-t sm:border-t-0 border-line">
                                        <div class="flex items-center rounded-md border border-line bg-paper">
                                            @if ($item['quantity'] > 1)
                                                <form action="{{ route('cart.update', $product) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="quantity" value="{{ $item['quantity'] - 1 }}">
                                                    <button type="submit" data-compact-btn aria-label="Diminuir quantidade" class="px-2.5 py-1 text-sm font-medium text-ink hover:bg-surface">−</button>
                                                </form>
                                            @else
                                                <form action="{{ route('cart.remove', $product) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" data-compact-btn aria-label="Remover item" class="px-2.5 py-1 text-sm font-medium text-danger-600 hover:bg-surface">−</button>
                                                </form>
                                            @endif

                                            <span class="min-w-[32px] border-x border-line bg-surface px-2 py-1 text-center font-mono tabular-nums text-xs font-medium text-ink">
                                                {{ $item['quantity'] }}
                                            </span>

                                            <form action="{{ route('cart.update', $product) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="quantity" value="{{ $item['quantity'] + 1 }}">
                                                <button type="submit" data-compact-btn aria-label="Aumentar quantidade" {{ $item['quantity'] >= $product->stock ? 'disabled' : '' }}
                                                        class="px-2.5 py-1 text-sm font-medium text-ink hover:bg-surface disabled:cursor-not-allowed disabled:text-slate-400">+</button>
                                            </form>
                                        </div>

                                        <div class="text-right min-w-[100px]">
                                            <span class="block font-mono tabular-nums text-sm font-semibold text-ink">
                                                R$ {{ number_format($item['subtotal'], 2, ',', '.') }}
                                            </span>
                                            <form action="{{ route('cart.remove', $product) }}" method="POST" class="mt-0.5">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-danger-600 hover:underline">Remover</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-surface rounded-lg border border-line p-5 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-semibold text-ink">Cupom / Condição comercial</h3>
                            @if(! empty($activeCoupon))
                                <form action="{{ route('cart.coupon.remove') }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-danger-600 hover:underline">
                                        Remover ({{ $activeCoupon }})
                                    </button>
                                </form>
                            @endif
                        </div>

                        <form action="{{ route('cart.coupon.apply') }}" method="POST" class="flex gap-2">
                            @csrf
                            <label for="coupon_code_input" class="sr-only">Código do cupom</label>
                            <input id="coupon_code_input" type="text" name="coupon_code" value="{{ $activeCoupon ?? '' }}"
                                   placeholder="Código do cupom (ex: {{ $placeholderCouponCode }})"
                                   class="flex-1 rounded-md border-line bg-paper text-xs font-mono py-2 px-3 focus:border-ink focus:ring-ink">
                            <button type="submit" class="border border-line bg-surface hover:border-ink text-ink font-medium text-xs px-4 py-2 rounded-md transition">
                                Aplicar
                            </button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <div class="sticky top-24 space-y-4 rounded-lg border border-line bg-surface p-6">
                        <h3 class="border-b border-line pb-3 font-serif text-2xl text-ink">Resumo do pedido</h3>

                        <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between text-slate-600">
                                <span>Subtotal dos insumos</span>
                                <span class="font-mono tabular-nums font-medium text-ink">R$ {{ number_format($subtotalAll ?? $total, 2, ',', '.') }}</span>
                            </div>

                            @if(($discountAmount ?? 0) > 0)
                                <div class="flex justify-between text-money-600 font-medium">
                                    <span>Desconto ({{ $activeCoupon }})</span>
                                    <span class="font-mono tabular-nums">− R$ {{ number_format($discountAmount, 2, ',', '.') }}</span>
                                </div>
                            @endif

                            <div class="flex justify-between text-slate-600">
                                <span>Frete {{ $savedCep ? '(CEP '.$savedCep.')' : '' }}</span>
                                @if($missingForFreeShipping <= 0)
                                    <span class="font-medium text-money-600">Grátis</span>
                                @elseif(($shippingCost ?? 0) > 0)
                                    <span class="font-mono tabular-nums font-medium text-ink">R$ {{ number_format($shippingCost, 2, ',', '.') }}</span>
                                @else
                                    <span class="text-slate-500">Informe o CEP</span>
                                @endif
                            </div>

                            <div class="flex items-baseline justify-between border-t border-line pt-3 text-sm font-semibold text-ink">
                                <span>Total em R$ / Créditos</span>
                                <div class="text-right">
                                    <span class="font-mono tabular-nums text-2xl text-ink block">R$ {{ number_format($total, 2, ',', '.') }}</span>
                                    <span class="font-mono tabular-nums text-xs text-money-700">{{ number_format($creditsRequired, 2, ',', '.') }} créditos</span>
                                </div>
                            </div>
                        </div>

                        @auth
                            @if($company && $wallet)
                                <div class="border-t border-line pt-3 text-xs space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-600">Empresa:</span>
                                        <span class="font-semibold text-ink">{{ $company->trade_name }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-600">Saldo em créditos:</span>
                                        <span class="font-mono tabular-nums font-semibold {{ $hasBalance ? 'text-money-600' : 'text-danger-600' }}">
                                            {{ number_format($balance, 2, ',', '.') }} créd.
                                        </span>
                                    </div>
                                    @if($hasBalance)
                                        <div class="flex items-center justify-between text-slate-500">
                                            <span>Saldo estimado após débito:</span>
                                            <span class="font-mono tabular-nums font-medium text-ink">{{ number_format($balance - $total, 2, ',', '.') }} créd.</span>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-between text-danger-600 pt-1">
                                            <span class="tabular-nums">Faltam {{ number_format($total - $balance, 2, ',', '.') }} créd.</span>
                                            <a href="{{ route('wallet.show') }}" class="font-medium underline">Contratar créditos →</a>
                                        </div>
                                    @endif

                                    @if($requiresApproval)
                                        <div class="rounded-md border border-accent-200 bg-accent-50 p-2.5 text-[11px] text-accent-950 mt-2">
                                            Este pedido será encaminhado para <strong>aprovação do Gestor da empresa</strong> antes do débito de créditos.
                                        </div>
                                    @endif
                                </div>

                                <form action="{{ route('orders.checkout') }}" method="POST"
                                      x-data="{ submitting: false }"
                                      @submit="submitting = true"
                                      class="space-y-3.5 pt-3 border-t border-line">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ $checkoutToken ?? session('checkout_token') }}">

                                    <div>
                                        <label for="checkout_cep" class="mb-1 block text-xs font-medium text-slate-700">CEP de entrega</label>
                                        <input id="checkout_cep" type="text" name="shipping_cep" value="{{ old('shipping_cep', $savedCep) }}"
                                               placeholder="00000-000"
                                               class="block w-full rounded-md border-line bg-paper text-xs font-mono tabular-nums py-2 focus:border-ink focus:ring-ink">
                                    </div>

                                    <div>
                                        <label for="checkout_address" class="mb-1 block text-xs font-medium text-slate-700">Endereço corporativo de entrega</label>
                                        <input id="checkout_address" type="text" name="shipping_address" value="{{ old('shipping_address', $savedAddress) }}" required
                                               placeholder="Rua, número, complemento, bairro, cidade - UF"
                                               class="block w-full rounded-md border-line bg-paper text-xs py-2 focus:border-ink focus:ring-ink">
                                    </div>

                                    <div>
                                        <label for="checkout_department" class="mb-1 block text-xs font-medium text-slate-700">Setor / Centro de custo (opcional)</label>
                                        <input id="checkout_department" type="text" name="department" value="{{ old('department', $membership?->department) }}"
                                               placeholder="Ex: Copa, Administrativo, Recepção"
                                               class="block w-full rounded-md border-line bg-paper text-xs py-2 focus:border-ink focus:ring-ink">
                                    </div>

                                    <button type="submit"
                                            :disabled="submitting || {{ (! $hasBalance || ! $wallet->is_active) ? 'true' : 'false' }}"
                                            class="w-full rounded-md bg-ink hover:bg-slate-800 disabled:bg-slate-200 disabled:text-slate-500 disabled:cursor-not-allowed px-4 py-3 text-xs font-medium text-paper transition flex items-center justify-center gap-2">
                                        <span x-text="submitting ? 'Processando…' : '{{ $requiresApproval ? 'Enviar para aprovação do gestor' : 'Confirmar pedido com créditos' }}'">
                                            {{ $requiresApproval ? 'Enviar para aprovação do gestor' : 'Confirmar pedido com créditos' }}
                                        </span>
                                    </button>
                                </form>
                            @else
                                <div class="pt-3 border-t border-line text-center text-xs text-danger-700">
                                    Sua conta não possui uma empresa vinculada para realizar pedidos.
                                </div>
                            @endif
                        @else
                            <div class="pt-3 border-t border-line text-center space-y-2.5">
                                <p class="text-xs text-slate-600">Entre com a conta da sua empresa para confirmar com saldo de créditos.</p>
                                <a href="{{ route('login') }}" class="block w-full rounded-md bg-ink hover:bg-slate-800 py-2.5 text-xs font-medium text-paper transition">
                                    Entrar para finalizar
                                </a>
                            </div>
                        @endauth

                        <p class="text-center text-xs text-slate-500 pt-1">
                            Modelo A: 1 crédito = R$ 1,00. Estorno integral em créditos em caso de cancelamento.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
