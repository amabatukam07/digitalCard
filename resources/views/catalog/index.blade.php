@php
    $pageTitle = $selectedCategory ? $selectedCategory->name : (request('q') ? 'Busca: ' . request('q') : 'Abastecimento Empresarial com Créditos Pré-Pagos');
    $pageDesc = $selectedCategory
        ? 'Insumos na categoria ' . $selectedCategory->name . ' para compras recorrentes empresariais com créditos pré-pagos NexoSupri.'
        : 'NexoSupri — Plataforma de abastecimento empresarial com créditos pré-pagos (Modelo A: 1 crédito = R$ 1,00). Sua empresa cuida dos negócios. A NexoSupri cuida das compras.';

    $hasSearch = trim((string) request('q')) !== '';
    $hasMinPrice = $effectiveMinPrice !== null;
    $hasMaxPrice = $effectiveMaxPrice !== null;
    $hasMinRating = request()->filled('min_rating');
    $hasAnyFilter = $hasSearch || $hasMinPrice || $hasMaxPrice || $hasMinRating || request()->filled('sort') || $selectedCategory;
    $activeFiltersCount = ($selectedCategory ? 1 : 0) + ($hasSearch ? 1 : 0) + (($hasMinPrice || $hasMaxPrice) ? 1 : 0) + ($hasMinRating ? 1 : 0);
    $freeShippingMin = (float) config('nexosupri.free_shipping_min_total', 500);
@endphp

<x-app-layout :metaTitle="$pageTitle" :metaDescription="$pageDesc">
    <div x-data="{ mobileFiltersOpen: false }" class="pb-4">

        @if($isHomeStorefront)
            @php
                $heroSlides = [
                    [
                        'image' => asset('images/hero/slide-1-escritorio.jpg'),
                        'alt' => 'Escritório corporativo e ergonomia NexoSupri',
                        'kicker' => 'Escritório & Ergonomia',
                        'tag' => 'Escritório & Ergonomia',
                        'href' => route('catalog.category', 'escritorio'),
                    ],
                    [
                        'image' => asset('images/hero/slide-2-copa.jpg'),
                        'alt' => 'Copa corporativa, café e bebidas NexoSupri',
                        'kicker' => 'Copa & Hospitalidade',
                        'tag' => 'Copa, Água & Bebidas',
                        'href' => route('catalog.category', 'agua-bebidas-nao-alcoolicas'),
                    ],
                    [
                        'image' => asset('images/hero/slide-3-tecnologia.jpg'),
                        'alt' => 'Tecnologia corporativa e áudio profissional NexoSupri',
                        'kicker' => 'Tecnologia Corporativa',
                        'tag' => 'Informática & Áudio Pro',
                        'href' => route('catalog.category', 'informatica'),
                    ],
                    [
                        'image' => asset('images/hero/slide-4-suprimentos.jpg'),
                        'alt' => 'Abastecimento e suprimentos corporativos NexoSupri',
                        'kicker' => 'Reposição Programada',
                        'tag' => 'Kits de Abastecimento',
                        'href' => route('bundles.index'),
                    ],
                ];
            @endphp

            {{-- Faixa 1: Hero Editorial com Carrossel Fotográfico Full-Bleed --}}
            <section x-data="{
                         active: 0,
                         direction: 1,
                         slideKey: 0,
                         total: {{ count($heroSlides) }},
                         paused: false,
                         timer: null,
                         slides: @js(collect($heroSlides)->map(fn (array $s): array => ['kicker' => $s['kicker'], 'tag' => $s['tag'], 'href' => $s['href']])->values()->all()),
                         start() {
                             if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                             this.stop();
                             this.timer = setInterval(() => {
                                 if (!this.paused) {
                                     this.direction = 1;
                                     this.active = (this.active + 1) % this.total;
                                     this.slideKey++;
                                 }
                             }, 4500);
                         },
                         restart() {
                             if (!this.paused) this.start();
                         },
                         stop() {
                             if (this.timer) {
                                 clearInterval(this.timer);
                                 this.timer = null;
                             }
                         },
                         prev() {
                             this.direction = -1;
                             this.active = (this.active - 1 + this.total) % this.total;
                             this.slideKey++;
                             this.restart();
                         },
                         next() {
                             this.direction = 1;
                             this.active = (this.active + 1) % this.total;
                             this.slideKey++;
                             this.restart();
                         },
                         go(i) {
                             this.direction = i >= this.active ? 1 : -1;
                             this.active = i;
                             this.slideKey++;
                             this.restart();
                         }
                     }"
                     x-init="start()"
                     @mouseenter="paused = true"
                     @mouseleave="paused = false"
                     @focusin="paused = true"
                     @focusout="paused = false"
                     :class="paused ? 'hero-paused' : ''"
                     aria-label="Destaques NexoSupri"
                     class="hero-dark-band relative overflow-hidden bg-ink text-paper border-b border-slate-800">

                {{-- Carrossel fotográfico em segundo plano --}}
                <div class="absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
                    @foreach($heroSlides as $slideIdx => $heroSlide)
                        <div :class="active === {{ $slideIdx }} ? 'opacity-100 translate-x-0 scale-100 z-[2]' : ('opacity-0 scale-105 z-[1] ' + (direction > 0 ? '-translate-x-4' : 'translate-x-4'))"
                             style="transition: opacity 650ms cubic-bezier(0.22, 1, 0.36, 1), transform 700ms cubic-bezier(0.22, 1, 0.36, 1);"
                             class="absolute inset-0">
                            <img src="{{ $heroSlide['image'] }}"
                                 alt="{{ $heroSlide['alt'] }}"
                                 width="1376"
                                 height="768"
                                 loading="{{ $slideIdx === 0 ? 'eager' : 'lazy' }}"
                                 @if($slideIdx === 0) fetchpriority="high" @else decoding="async" @endif
                                 :class="active === {{ $slideIdx }} ? 'hero-ken-burns-active' : ''"
                                 class="h-full w-full object-cover select-none">
                        </div>
                    @endforeach
                    <div class="absolute inset-0 z-[3] hero-scrim"></div>
                </div>

                <div class="relative z-10 app-shell pt-10 pb-6 sm:pt-14 sm:pb-8 lg:pt-16 lg:pb-10">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                        <div class="lg:col-span-7 space-y-4 max-w-2xl">
                            <a :href="slides[active]?.href || '#catalogo'"
                               class="inline-flex items-center gap-2 text-xs font-semibold tracking-[0.14em] uppercase text-accent-300 hover:text-paper transition-colors">
                                <span class="relative flex size-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full size-2 bg-accent-400"></span>
                                </span>
                                <span x-text="slides[active]?.kicker || 'Escritório & Ergonomia'" class="animate-hero-caption">Escritório &amp; Ergonomia</span>
                            </a>

                            <h1 class="font-serif hero-display text-paper drop-shadow-sm">
                                Abastecimento corporativo
                                <span class="italic text-accent-300">em um só lugar.</span>
                            </h1>

                            <p class="text-sm sm:text-base text-slate-200 max-w-lg leading-relaxed">
                                Centralize as compras de toda a empresa em uma única carteira, com aprovação por gestor e entrega ágil.
                            </p>

                            <div class="flex flex-wrap items-center gap-3 pt-1">
                                <a href="#catalogo"
                                   class="inline-flex items-center gap-2 bg-paper hover:bg-slate-200 active:scale-[0.98] text-ink text-sm font-semibold px-5 py-2.5 rounded-lg transition-all duration-200 shadow-sm whitespace-nowrap">
                                    <span>Explorar catálogo</span>
                                    <x-icon name="arrow-right" class="size-4" />
                                </a>
                                <a href="{{ route('plans.index') }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg border border-paper/30 bg-ink/35 hover:bg-ink/60 hover:border-paper/70 backdrop-blur-md text-sm font-medium text-paper transition-colors duration-200 whitespace-nowrap">
                                    <span>Ver planos</span>
                                </a>
                            </div>
                        </div>

                        <div class="lg:col-span-5 max-w-[430px] mx-auto lg:ml-auto lg:mr-0 w-full space-y-2">
                            <x-virtual-card :company="$currentCompany" :wallet="$currentWallet" :planName="$currentPlanName ?? null" />

                            <div class="flex items-center justify-end text-xs px-1">
                                @auth
                                    <a href="{{ route('wallet.show') }}" class="font-semibold text-accent-300 hover:underline whitespace-nowrap">Abrir carteira →</a>
                                @else
                                    <a href="{{ route('plans.index') }}" class="font-semibold text-accent-300 hover:underline whitespace-nowrap">Ativar cartão corporativo →</a>
                                @endauth
                            </div>
                        </div>
                    </div>

                    {{-- Controles discretos do carrossel com barra de progresso ao vivo --}}
                    <div class="mt-8 sm:mt-10 pt-4 border-t border-paper/15 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 sm:gap-4 overflow-x-auto no-scrollbar">
                            @foreach($heroSlides as $slideIdx => $heroSlide)
                                <button type="button"
                                        data-compact-btn
                                        @click="go({{ $slideIdx }})"
                                        :aria-current="active === {{ $slideIdx }} ? 'true' : 'false'"
                                        aria-label="Ver destaque: {{ $heroSlide['tag'] }}"
                                        class="group inline-flex items-center gap-2 py-1 text-xs transition shrink-0">
                                    <span :class="active === {{ $slideIdx }} ? 'w-10 bg-paper/25' : 'w-3 bg-paper/25 group-hover:bg-paper/50'"
                                          class="h-1.5 rounded-full overflow-hidden transition-all duration-300 block">
                                        <template x-if="active === {{ $slideIdx }}">
                                            <span :key="slideKey" class="block h-full w-full bg-paper hero-progress-bar"></span>
                                        </template>
                                    </span>
                                    <span :class="active === {{ $slideIdx }} ? 'text-paper font-medium' : 'text-slate-300/75 group-hover:text-paper'"
                                          class="hidden sm:inline transition-colors">
                                        {{ $heroSlide['tag'] }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="hidden sm:inline font-mono text-[11px] text-slate-300 tabular-nums mr-1"
                                  x-text="String(active + 1).padStart(2, '0') + ' / ' + String(total).padStart(2, '0')">01 / 04</span>

                            <button type="button"
                                    data-compact-btn
                                    @click="prev()"
                                    aria-label="Imagem anterior do destaque"
                                    class="h-8 w-8 rounded-full bg-ink/45 border border-paper/25 hover:bg-ink/75 hover:scale-105 active:scale-95 text-paper backdrop-blur-md flex items-center justify-center transition">
                                <x-icon name="arrow-left" class="size-4" />
                            </button>
                            <button type="button"
                                    data-compact-btn
                                    @click="next()"
                                    aria-label="Próxima imagem do destaque"
                                    class="h-8 w-8 rounded-full bg-ink/45 border border-paper/25 hover:bg-ink/75 hover:scale-105 active:scale-95 text-paper backdrop-blur-md flex items-center justify-center transition">
                                <x-icon name="arrow-right" class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Faixa 2: Cupons Corporativos Full-Bleed --}}
            @if($activeCoupons->isNotEmpty())
                <section x-data="{ copiedCode: '' }"
                         aria-label="Cupons corporativos ativos"
                         class="bg-surface border-b border-line">
                    <div class="app-shell py-2.5 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <div class="flex items-center gap-1.5 text-ink font-semibold shrink-0">
                                <x-icon name="tag" class="size-4 text-accent-600" />
                                <span>Cupons corporativos:</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @foreach($activeCoupons as $coupon)
                                    <button type="button"
                                            data-compact-btn
                                            @click="navigator.clipboard?.writeText(@js($coupon->code)); copiedCode = @js($coupon->code); setTimeout(() => { if (copiedCode === @js($coupon->code)) copiedCode = ''; }, 2500)"
                                            title="{{ $coupon->label }}"
                                            class="inline-flex items-center gap-2 rounded-lg border border-line bg-paper hover:border-ink px-2.5 py-1 transition">
                                        <span class="font-mono font-bold text-ink tracking-wide">{{ $coupon->code }}</span>
                                        <span class="text-money-700 font-semibold tabular-nums">
                                            {{ $coupon->type === \App\Models\Coupon::TYPE_PERCENT ? rtrim(rtrim(number_format((float) $coupon->value, 2, ',', '.'), '0'), ',').'% OFF' : 'R$ '.number_format((float) $coupon->value, 0, ',', '.').' OFF' }}
                                        </span>
                                        <span class="text-[11px] text-slate-500 hidden sm:inline">
                                            <span x-show="copiedCode !== @js($coupon->code)">Copiar</span>
                                            <span x-show="copiedCode === @js($coupon->code)" x-cloak class="text-money-700 font-semibold">Copiado!</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="hidden xl:flex items-center gap-4 text-[11px] text-slate-600 shrink-0">
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="shield-check" class="size-3.5 text-money-600" />
                                <span>Aprovação por gestor</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="truck" class="size-3.5 text-accent-600" />
                                <span>Logística empresarial B2B</span>
                            </span>
                        </div>
                        <span class="sr-only" aria-live="polite" x-text="copiedCode ? 'Cupom ' + copiedCode + ' copiado' : ''"></span>
                    </div>
                </section>
            @endif
        @endif

        {{-- Faixa 3: Catálogo Principal Intercalado --}}
        <section id="catalogo" class="app-shell py-6 sm:py-8">
            <div class="grid grid-cols-1 lg:grid-cols-[292px_minmax(0,1fr)] xl:grid-cols-[308px_minmax(0,1fr)] gap-6 items-start">
                <aside class="hidden lg:block lg:sticky lg:top-28 bg-surface rounded-none rounded-tr-[28px] rounded-bl-[28px] border border-line border-t-[3px] border-t-ink overflow-hidden shadow-card">
                    <div class="bg-paper/80 px-5 py-4 border-b border-line flex items-center justify-between gap-2">
                        <h2 class="font-serif text-2xl text-ink leading-none">Categorias</h2>
                        @if($selectedCategory)
                            <a href="{{ route('catalog.index') }}" class="text-xs font-medium text-accent-600 hover:underline">Ver todas</a>
                        @else
                            <span class="text-[11px] font-mono tabular-nums text-slate-500">
                                {{ array_sum($parentCategoryCounts) }} itens
                            </span>
                        @endif
                    </div>

                    <div class="p-5 space-y-5">
                        <ul class="space-y-1 text-[13px]">
                            <li>
                                <a href="{{ route('catalog.index') }}"
                                   class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-none border-l-[3px] transition-colors {{ ! $selectedCategory ? 'border-l-ink bg-ink text-paper font-medium' : 'border-l-transparent text-slate-700 hover:border-l-slate-400 hover:bg-paper hover:text-ink' }}">
                                    <span class="flex items-center gap-2.5">
                                        <x-icon name="layers" class="size-4 shrink-0 {{ ! $selectedCategory ? 'text-accent-400' : 'text-slate-500' }}" />
                                        <span>Todas as categorias</span>
                                    </span>
                                    <span class="text-[11px] font-mono tabular-nums {{ ! $selectedCategory ? 'text-slate-300' : 'text-slate-500' }}">
                                        {{ array_sum($parentCategoryCounts) }}
                                    </span>
                                </a>
                            </li>
                            @foreach($categories as $cat)
                                @php
                                    $isParentSelected = $selectedCategory?->id === $cat->id;
                                    $isChildSelected = $selectedCategory && (int) $selectedCategory->parent_id === (int) $cat->id;
                                    $branchOpen = $isParentSelected || $isChildSelected;
                                    $catCount = $parentCategoryCounts[$cat->id] ?? 0;
                                @endphp
                                <li x-data="{ open: {{ $branchOpen ? 'true' : 'false' }} }" class="pt-1.5 border-t border-line/70">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('catalog.category', $cat->slug) }}"
                                           class="flex-1 min-w-0 flex items-center justify-between gap-2 px-2.5 py-2 rounded-none border-l-[3px] transition-colors {{ $isParentSelected ? 'border-l-accent-600 bg-accent-50/70 text-accent-700 font-semibold' : 'border-l-transparent font-medium text-ink hover:border-l-ink/40 hover:bg-paper/80' }}">
                                            <span class="flex items-center gap-2.5 min-w-0">
                                                <span class="h-7 w-7 rounded-sm flex items-center justify-center shrink-0 border {{ $isParentSelected ? 'bg-accent-100 border-accent-200 text-accent-700' : 'bg-paper border-line/80 text-slate-600' }}">
                                                    <x-icon :name="$cat->icon ?: 'package'" class="size-3.5" />
                                                </span>
                                                <span class="leading-snug">{{ $cat->name }}</span>
                                            </span>
                                            <span class="font-mono text-[11px] tabular-nums text-slate-500 shrink-0">{{ $catCount }}</span>
                                        </a>
                                        @if($cat->children->isNotEmpty())
                                            <button type="button"
                                                    data-compact-btn
                                                    @click="open = !open"
                                                    :aria-expanded="open ? 'true' : 'false'"
                                                    aria-label="Alternar subcategorias de {{ $cat->name }}"
                                                    class="h-7 w-7 rounded-sm hover:bg-paper flex items-center justify-center text-slate-500 shrink-0 transition-transform"
                                                    :class="open ? 'rotate-180' : ''">
                                                <x-icon name="chevron-down" class="size-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                    @if($cat->children->isNotEmpty())
                                        <ul x-show="open" @if(! $branchOpen) x-cloak @endif class="mt-1.5 ml-3.5 pl-3 border-l border-line space-y-1">
                                            @foreach($cat->children as $child)
                                                <li>
                                                    <a href="{{ route('catalog.category', $child->slug) }}"
                                                       class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-none border-l-2 text-xs transition-colors {{ $selectedCategory?->id === $child->id ? 'border-l-accent-600 bg-accent-50/60 text-accent-700 font-semibold' : 'border-l-transparent text-slate-600 hover:border-l-slate-400 hover:bg-paper hover:text-ink' }}">
                                                        <span class="leading-snug">{{ $child->name }}</span>
                                                        <span class="font-mono tabular-nums text-[11px] text-slate-400 shrink-0">{{ $categoryProductCounts[$child->id] ?? 0 }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <form action="{{ $selectedCategory ? route('catalog.category', $selectedCategory->slug) : route('catalog.index') }}" method="GET" class="space-y-3.5 pt-4 border-t border-line text-xs">
                            @if($hasSearch)
                                <input type="hidden" name="q" value="{{ request('q') }}">
                            @endif
                            @if(request('sort'))
                                <input type="hidden" name="sort" value="{{ request('sort') }}">
                            @endif

                            <div class="space-y-2">
                                <span class="font-semibold text-ink block">Faixa de valor</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @php
                                        $pricePresets = [
                                            ['label' => 'Até R$ 50', 'min' => null, 'max' => 50],
                                            ['label' => 'R$ 50–300', 'min' => 50, 'max' => 300],
                                            ['label' => 'R$ 300+', 'min' => 300, 'max' => null],
                                        ];
                                    @endphp
                                    @foreach($pricePresets as $preset)
                                        @php
                                            $isPresetActive = ($effectiveMinPrice === ($preset['min'] !== null ? (float) $preset['min'] : null))
                                                && ($effectiveMaxPrice === ($preset['max'] !== null ? (float) $preset['max'] : null));
                                        @endphp
                                        <a href="{{ request()->fullUrlWithQuery(['min_price' => $preset['min'], 'max_price' => $preset['max'], 'page' => null]) }}"
                                           class="px-2.5 py-1 rounded-none border text-[11px] font-medium transition-colors {{ $isPresetActive ? 'bg-ink text-paper border-ink' : 'bg-paper text-slate-600 border-line hover:border-ink hover:text-ink' }}">
                                            {{ $preset['label'] }}
                                        </a>
                                    @endforeach
                                </div>

                                <div class="grid grid-cols-2 gap-2 pt-0.5">
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] text-slate-400">R$</span>
                                        <input type="number" name="min_price" value="{{ $effectiveMinPrice }}" placeholder="Mín." min="0" step="1"
                                               aria-label="Valor mínimo em reais"
                                               class="w-full rounded-sm border-line bg-paper text-xs py-2 pl-7 pr-2.5 tabular-nums focus:border-ink focus:ring-ink">
                                    </div>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] text-slate-400">R$</span>
                                        <input type="number" name="max_price" value="{{ $effectiveMaxPrice }}" placeholder="Máx." min="0" step="1"
                                               aria-label="Valor máximo em reais"
                                               class="w-full rounded-sm border-line bg-paper text-xs py-2 pl-7 pr-2.5 tabular-nums focus:border-ink focus:ring-ink">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label for="sidebar_min_rating" class="font-semibold text-ink block">Avaliação mínima</label>
                                <select id="sidebar_min_rating" name="min_rating"
                                        class="w-full rounded-sm border-line bg-paper text-xs py-2 px-3 focus:border-ink focus:ring-ink">
                                    <option value="" {{ ! $hasMinRating ? 'selected' : '' }}>Todas as avaliações</option>
                                    <option value="4" {{ request('min_rating') == '4' ? 'selected' : '' }}>4+ estrelas</option>
                                    <option value="3" {{ request('min_rating') == '3' ? 'selected' : '' }}>3+ estrelas</option>
                                </select>
                            </div>

                            <div class="flex gap-2 pt-0.5">
                                <button type="submit" class="flex-1 bg-ink hover:bg-slate-800 active:scale-[0.98] text-paper font-semibold py-2.5 px-3 rounded-none rounded-tr-xl rounded-bl-xl transition-all">
                                    Aplicar filtros
                                </button>
                                @if($hasAnyFilter)
                                    <a href="{{ route('catalog.index') }}" class="px-3.5 py-2.5 rounded-none border border-line bg-surface text-slate-700 hover:border-ink transition-colors font-medium flex items-center">
                                        Limpar
                                    </a>
                                @endif
                            </div>
                        </form>

                        <div class="rounded-none border-l-[3px] border-l-money-600 bg-money-50/75 px-3.5 py-2.5 flex items-center gap-2.5 text-xs text-money-700 font-medium">
                            <x-icon name="truck" class="size-4 shrink-0" />
                            <span>Frete grátis acima de R$ {{ number_format($freeShippingMin, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </aside>

                <div class="min-w-0 space-y-4">
                    <div class="bg-surface rounded-xl border border-line p-4 flex flex-col gap-3 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center flex-wrap gap-x-3 gap-y-1.5 min-w-0">
                                <h2 class="font-serif text-2xl sm:text-3xl text-ink">
                                    {{ $selectedCategory ? $selectedCategory->name : ($hasSearch ? 'Resultados para “' . request('q') . '”' : 'Catálogo de Insumos') }}
                                </h2>
                                <span class="text-xs text-slate-500 tabular-nums">
                                    ({{ $products->total() }} {{ $products->total() === 1 ? 'item encontrado' : 'itens encontrados' }})
                                </span>
                                @if(! $selectedCategory && ! $hasSearch && ! request('sort'))
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-paper border border-line text-[11px] font-medium text-accent-700">
                                        <x-icon name="sparkles" class="size-3" />
                                        <span>Mix alternado · {{ $categories->count() }} categorias</span>
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button @click="mobileFiltersOpen = true" type="button"
                                        class="lg:hidden inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-line bg-paper text-xs font-medium text-ink">
                                    <x-icon name="sliders-horizontal" class="size-3.5" />
                                    <span>Filtrar</span>
                                    @if($activeFiltersCount > 0)
                                        <span class="px-1.5 py-0.5 rounded-full bg-ink text-paper font-mono text-[10px] font-semibold tabular-nums">{{ $activeFiltersCount }}</span>
                                    @endif
                                </button>

                                <form action="{{ $selectedCategory ? route('catalog.category', $selectedCategory->slug) : route('catalog.index') }}" method="GET" class="flex items-center gap-2">
                                    @foreach(request()->except(['sort', 'page', 'category']) as $k => $v)
                                        @if(is_scalar($v))
                                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                        @endif
                                    @endforeach
                                    @if(request()->filled('category'))
                                        <input type="hidden" name="category" value="{{ request('category') }}">
                                    @endif
                                    <label for="sort-select" class="text-xs text-slate-500 hidden sm:inline">Ordenar:</label>
                                    <select id="sort-select" name="sort" onchange="this.form.submit()"
                                            class="rounded-lg border-line bg-paper text-xs text-ink py-1.5 pl-2.5 pr-7 focus:border-ink focus:ring-ink">
                                        <option value="" {{ ! request('sort') ? 'selected' : '' }}>{{ $selectedCategory || $hasSearch ? 'Relevância' : 'Mix alternado' }}</option>
                                        <option value="menor_preco" {{ request('sort') === 'menor_preco' ? 'selected' : '' }}>Menor preço</option>
                                        <option value="maior_preco" {{ request('sort') === 'maior_preco' ? 'selected' : '' }}>Maior preço</option>
                                        <option value="maior_desconto" {{ request('sort') === 'maior_desconto' ? 'selected' : '' }}>Maior desconto</option>
                                        <option value="melhor_avaliados" {{ request('sort') === 'melhor_avaliados' ? 'selected' : '' }}>Melhor avaliados</option>
                                    </select>
                                </form>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-2 border-t border-line text-xs">
                            <a href="{{ route('catalog.index') }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border transition-colors shrink-0 {{ ! $selectedCategory && ! request('sort') ? 'bg-ink text-paper border-ink font-medium' : 'bg-paper text-slate-700 border-line hover:border-ink' }}">
                                <span>Todos</span>
                            </a>
                            @foreach($categories as $quickCat)
                                @php
                                    $isQuickActive = $selectedCategory?->id === $quickCat->id || (int) $selectedCategory?->parent_id === (int) $quickCat->id;
                                @endphp
                                <a href="{{ route('catalog.category', $quickCat->slug) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border transition-colors shrink-0 {{ $isQuickActive ? 'bg-ink text-paper border-ink font-medium' : 'bg-paper text-slate-700 border-line hover:border-ink' }}">
                                    <span>{{ $quickCat->name }}</span>
                                    <span class="font-mono text-[10px] tabular-nums {{ $isQuickActive ? 'text-slate-300' : 'text-slate-500' }}">
                                        {{ $parentCategoryCounts[$quickCat->id] ?? 0 }}
                                    </span>
                                </a>
                            @endforeach
                        </div>

                        @if($hasSearch || $hasMinPrice || $hasMaxPrice || $hasMinRating)
                            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-line text-[11px]">
                                <span class="text-slate-500">Filtros ativos:</span>
                                @if($hasSearch)
                                    <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}"
                                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-paper border border-line text-ink hover:border-ink">
                                        <span>Busca: {{ request('q') }}</span>
                                        <x-icon name="x" class="size-3" />
                                    </a>
                                @endif
                                @if($hasMinPrice || $hasMaxPrice)
                                    <a href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => null, 'page' => null]) }}"
                                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-paper border border-line text-ink hover:border-ink tabular-nums">
                                        <span>
                                            Preço: {{ $hasMinPrice ? 'R$ '.number_format($effectiveMinPrice, 0, ',', '.') : '0' }} – {{ $hasMaxPrice ? 'R$ '.number_format($effectiveMaxPrice, 0, ',', '.') : '∞' }}
                                        </span>
                                        <x-icon name="x" class="size-3" />
                                    </a>
                                @endif
                                @if($hasMinRating)
                                    <a href="{{ request()->fullUrlWithQuery(['min_rating' => null, 'page' => null]) }}"
                                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-paper border border-line text-ink hover:border-ink">
                                        <span>Nota {{ request('min_rating') }}+</span>
                                        <x-icon name="x" class="size-3" />
                                    </a>
                                @endif
                                <a href="{{ $selectedCategory ? route('catalog.category', $selectedCategory->slug) : route('catalog.index') }}"
                                   class="text-accent-600 hover:underline font-medium ml-auto">
                                    Limpar filtros
                                </a>
                            </div>
                        @endif
                    </div>

                    @if($products->isEmpty())
                        <div class="bg-surface rounded-xl border border-line p-10 text-center space-y-3">
                            <h3 class="font-serif text-xl text-ink">Nenhum insumo encontrado</h3>
                            <p class="text-xs text-slate-500">Tente remover filtros ou buscar outro termo.</p>
                            <a href="{{ route('catalog.index') }}" class="inline-block bg-ink text-paper text-xs font-medium px-4 py-2 rounded-lg">
                                Limpar filtros
                            </a>
                        </div>
                    @else
                        <div class="grid grid-cols-1 min-[440px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
                            @foreach($products as $product)
                                <x-product-card :product="$product" :wishlistIds="$wishlistIds" :eager="$loop->index < 4" />
                            @endforeach
                        </div>

                        <div class="pt-3">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>

        @if($isHomeStorefront)
            {{-- Faixa 4: Kits de Abastecimento Corporativo Full-Bleed --}}
            @if($featuredBundles->isNotEmpty())
                <section class="bg-paper-dim/55 border-y border-line py-8 sm:py-10">
                    <div class="app-shell space-y-5">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <x-eyebrow>Reposição rápida</x-eyebrow>
                                <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5 flex items-center gap-2">
                                    <x-icon name="layers" class="size-6 text-accent-600" />
                                    <span>Kits de Abastecimento Corporativo</span>
                                </h2>
                            </div>
                            <a href="{{ route('bundles.index') }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-ink hover:text-accent-600 transition-colors shrink-0">
                                <span>Ver todos os kits</span>
                                <x-icon name="arrow-right" class="size-3.5" />
                            </a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            @foreach($featuredBundles as $homeBundle)
                                @php
                                    $bTotal = $homeBundle->totalPrice();
                                    $bundleProducts = $homeBundle->items->pluck('product')->filter();
                                @endphp
                                <article class="bg-surface rounded-2xl border border-line hover:border-ink p-5 flex flex-col justify-between gap-4 transition shadow-sm">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between gap-2">
                                            @if($homeBundle->badge)
                                                <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 bg-paper border border-line text-[11px] font-semibold text-accent-700">
                                                    <x-icon name="sparkles" class="size-3" />
                                                    <span>{{ $homeBundle->badge }}</span>
                                                </span>
                                            @else
                                                <span></span>
                                            @endif
                                            <span class="text-[11px] font-mono text-slate-500 tabular-nums">{{ $bundleProducts->count() }} itens no kit</span>
                                        </div>

                                        <div>
                                            <h3 class="font-serif text-xl text-ink">{{ $homeBundle->name }}</h3>
                                            @if($homeBundle->subtitle)
                                                <p class="text-xs text-slate-600 line-clamp-2 mt-1">{{ $homeBundle->subtitle }}</p>
                                            @endif
                                        </div>

                                        @if($bundleProducts->isNotEmpty())
                                            <div class="flex items-center gap-2 pt-1">
                                                @foreach($bundleProducts->take(4) as $bProd)
                                                    <a href="{{ route('catalog.product', $bProd->slug) }}"
                                                       title="{{ $bProd->name }}"
                                                       class="h-11 w-11 rounded-lg border border-line bg-white overflow-hidden p-1 shrink-0 hover:border-ink transition">
                                                        <img src="{{ $bProd->thumbnail_url }}" alt="{{ $bProd->name }}" width="44" height="44" loading="lazy"
                                                             class="h-full w-full object-contain">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="pt-4 border-t border-line flex flex-wrap items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="font-mono text-lg sm:text-xl font-bold tabular-nums text-ink block whitespace-nowrap">
                                                R$ {{ number_format($bTotal, 2, ',', '.') }}
                                            </span>
                                            <span class="text-[11px] font-mono text-slate-500 tabular-nums whitespace-nowrap block">
                                                {{ number_format($bTotal, 2, ',', '.') }} créditos
                                            </span>
                                        </div>

                                        <form action="{{ route('bundles.add-to-cart', $homeBundle->slug) }}" method="POST" class="shrink-0">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-ink hover:bg-slate-800 active:scale-[0.98] px-3.5 py-2 text-xs font-semibold text-paper transition whitespace-nowrap">
                                                <x-icon name="shopping-cart" class="size-3.5 shrink-0" />
                                                <span>Adicionar kit</span>
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- Faixa 5: Vitrine Pós-Kits #1 — Continue Explorando (12 novos itens multi-categoria) --}}
            @if($postKitDiscoveryProducts->isNotEmpty())
                <section class="app-shell py-8 sm:py-10 space-y-5"
                         x-data="{
                             scrolling: false,
                             scrollRail(dir) {
                                 const el = this.$refs.rail;
                                 if (!el || this.scrolling) return;
                                 const distance = dir * Math.max(300, Math.floor(el.clientWidth * 0.72));
                                 if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                     el.scrollLeft += distance;
                                     return;
                                 }
                                 const start = el.scrollLeft;
                                 const duration = 950;
                                 const startTime = performance.now();
                                 const prevSnap = el.style.scrollSnapType;
                                 el.style.scrollSnapType = 'none';
                                 this.scrolling = true;
                                 const ease = (t) => t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
                                 const step = (now) => {
                                     const progress = Math.min(1, (now - startTime) / duration);
                                     el.scrollLeft = start + distance * ease(progress);
                                     if (progress < 1) {
                                         requestAnimationFrame(step);
                                     } else {
                                         el.style.scrollSnapType = prevSnap;
                                         this.scrolling = false;
                                     }
                                 };
                                 requestAnimationFrame(step);
                             }
                         }">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <x-eyebrow>Mais variedade para sua empresa</x-eyebrow>
                            <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5">
                                Continue explorando o catálogo
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-600 mt-1">
                                Nova seleção multi-setor intercalada em todas as linhas B2B, pronta para pedido imediato.
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button"
                                    data-compact-btn
                                    @click="scrollRail(-1)"
                                    aria-label="Rolar vitrine para a esquerda"
                                    class="h-9 w-9 rounded-lg border border-line bg-surface hover:border-ink flex items-center justify-center text-ink transition">
                                <x-icon name="arrow-left" class="size-4" />
                            </button>
                            <button type="button"
                                    data-compact-btn
                                    @click="scrollRail(1)"
                                    aria-label="Rolar vitrine para a direita"
                                    class="h-9 w-9 rounded-lg border border-line bg-surface hover:border-ink flex items-center justify-center text-ink transition">
                                <x-icon name="arrow-right" class="size-4" />
                            </button>
                            <a href="{{ route('catalog.index', ['page' => 2]) }}"
                               class="inline-flex items-center gap-1.5 ml-1 px-3 py-2 rounded-lg border border-line bg-surface hover:border-ink text-xs font-medium text-ink transition whitespace-nowrap">
                                <span>Ver próxima página</span>
                                <x-icon name="arrow-right" class="size-3.5" />
                            </a>
                        </div>
                    </div>

                    <div x-ref="rail" class="flex gap-4 sm:gap-5 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-2">
                        @foreach($postKitDiscoveryProducts as $discProduct)
                            <div class="w-[262px] sm:w-[278px] lg:w-[292px] shrink-0 snap-start">
                                <x-product-card :product="$discProduct" :wishlistIds="$wishlistIds" :eager="false" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Faixa 6: Vitrine Pós-Kits #2 — Vitrine Dinâmica por Departamento --}}
            @if(! empty($departmentShowcaseTabs))
                @php
                    $allTab = collect($departmentShowcaseTabs)->firstWhere('key', 'all');
                    $deptTabsPayload = [];
                    foreach ($departmentShowcaseTabs as $deptTab) {
                        if ($deptTab['key'] === 'all') {
                            continue;
                        }
                        $deptTabsPayload[$deptTab['key']] = [
                            'label' => $deptTab['label'],
                            'category_url' => $deptTab['slug'] ? route('catalog.category', $deptTab['slug']) : route('catalog.index', ['page' => 2]),
                            'products' => $deptTab['products']->map(fn (\App\Models\Product $p): array => [
                                'id' => (int) $p->id,
                                'name' => $p->name,
                                'category_name' => $p->category->name ?? 'Catálogo',
                                'presentation_line' => ($p->presentation ?: $p->unit).($p->brand ? ' · '.$p->brand : ''),
                                'sku' => $p->sku,
                                'discount_percent' => (int) $p->discount_percent,
                                'price_formatted' => 'R$ '.number_format((float) $p->price, 2, ',', '.'),
                                'compare_at_formatted' => $p->discount_percent > 0 ? 'R$ '.number_format((float) $p->compare_at_price, 2, ',', '.') : '',
                                'credits_formatted' => number_format((float) $p->price, 2, ',', '.').' créd.',
                                'rating_formatted' => number_format((float) $p->rating, 1, ',', '.'),
                                'reviews_count' => (int) $p->reviews_count,
                                'thumbnail_url' => $p->thumbnail_url,
                                'url' => route('catalog.product', $p->slug),
                                'cart_url' => route('cart.add', $p),
                                'is_low_stock' => (bool) $p->is_low_stock,
                                'stock' => (int) $p->stock,
                            ])->values()->all(),
                        ];
                    }
                @endphp

                <section class="bg-surface border-y border-line py-8 sm:py-10"
                         x-data="{ activeTab: 'all', tabsData: @js($deptTabsPayload) }">
                    <div class="app-shell space-y-5">
                        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                            <div>
                                <x-eyebrow>Descoberta por linha corporativa</x-eyebrow>
                                <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5">
                                    Explore novas combinações por departamento
                                </h2>
                            </div>

                            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1" role="tablist" aria-label="Departamentos em destaque">
                                @foreach($departmentShowcaseTabs as $tab)
                                    <button type="button"
                                            role="tab"
                                            :aria-selected="activeTab === @js($tab['key']) ? 'true' : 'false'"
                                            @click="activeTab = @js($tab['key'])"
                                            :class="activeTab === @js($tab['key']) ? 'bg-ink text-paper border-ink' : 'bg-paper text-slate-700 border-line hover:border-ink'"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-medium transition-colors shrink-0 whitespace-nowrap">
                                        <x-icon :name="$tab['icon']" class="size-3.5 shrink-0" />
                                        <span>{{ $tab['label'] }}</span>
                                        <span class="font-mono text-[10px] tabular-nums opacity-75">{{ $tab['count'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if($allTab)
                            <div x-show="activeTab === 'all'" class="space-y-4">
                                <div class="grid grid-cols-1 min-[440px]:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                                    @foreach($allTab['products'] as $tabProduct)
                                        <x-product-card :product="$tabProduct" :wishlistIds="$wishlistIds" :eager="false" />
                                    @endforeach
                                </div>

                                <div class="flex justify-end">
                                    <a href="{{ route('catalog.index', ['page' => 2]) }}"
                                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink hover:text-accent-600 transition-colors">
                                        <span>Ver mais combinações no catálogo</span>
                                        <x-icon name="arrow-right" class="size-3.5" />
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div x-show="activeTab !== 'all'" x-cloak class="space-y-4">
                            <div class="grid grid-cols-1 min-[440px]:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                                <template x-for="item in (tabsData[activeTab]?.products || [])" :key="item.id">
                                    <article class="bg-surface rounded-xl border border-line hover:border-ink/40 hover:-translate-y-1 hover:shadow-float transition-all duration-300 ease-out flex flex-col overflow-hidden group">
                                        <div class="relative border-b border-line bg-white product-photo-box overflow-hidden aspect-[4/3] flex items-center justify-center p-4">
                                            <a :href="item.url" class="block h-full w-full flex items-center justify-center">
                                                <img :src="item.thumbnail_url" :alt="item.name" width="320" height="240" loading="lazy" decoding="async"
                                                     class="max-h-full max-w-full object-contain transition-transform duration-300 group-hover:scale-105">
                                            </a>

                                            <template x-if="item.discount_percent > 0">
                                                <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md bg-accent-600 text-paper text-xs font-semibold tabular-nums shadow-sm pointer-events-none"
                                                      x-text="'−' + item.discount_percent + '%'"></span>
                                            </template>
                                            <template x-if="item.discount_percent <= 0 && item.sku">
                                                <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md bg-ink/85 text-paper font-mono text-[10px] font-medium pointer-events-none"
                                                      x-text="item.sku"></span>
                                            </template>
                                        </div>

                                        <div class="p-3.5 sm:p-4 flex-1 flex flex-col justify-between gap-2.5">
                                            <div class="space-y-1.5">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-[11px] font-medium text-slate-600 bg-paper border border-line/80 px-2 py-0.5 rounded-md truncate max-w-[68%]"
                                                          x-text="item.category_name"></span>

                                                    <template x-if="item.reviews_count > 0">
                                                        <div class="flex items-center gap-1 shrink-0">
                                                            <x-icon name="star" class="size-3.5 text-accent-500" />
                                                            <span class="text-xs tabular-nums font-medium text-ink" x-text="item.rating_formatted"></span>
                                                            <span class="text-xs text-slate-400 tabular-nums" x-text="'(' + item.reviews_count + ')'"></span>
                                                        </div>
                                                    </template>
                                                </div>

                                                <h3 class="text-sm font-medium text-ink group-hover:text-accent-600 transition-colors line-clamp-2 leading-snug min-h-[2.5rem]">
                                                    <a :href="item.url" x-text="item.name"></a>
                                                </h3>

                                                <template x-if="item.presentation_line">
                                                    <p class="text-[11px] text-slate-500 truncate" x-text="item.presentation_line"></p>
                                                </template>
                                            </div>

                                            <div class="pt-2.5 border-t border-line space-y-2.5">
                                                <div class="space-y-1">
                                                    <div class="flex items-baseline flex-wrap gap-x-2 gap-y-0.5">
                                                        <span class="text-base sm:text-lg font-semibold text-ink tabular-nums whitespace-nowrap"
                                                              x-text="item.price_formatted"></span>
                                                        <template x-if="item.compare_at_formatted">
                                                            <span class="text-xs text-slate-400 line-through tabular-nums whitespace-nowrap"
                                                                  x-text="item.compare_at_formatted"></span>
                                                        </template>
                                                    </div>

                                                    <div class="flex items-center justify-between gap-1 flex-wrap">
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-paper border border-line text-[11px] font-mono font-medium text-ink tabular-nums"
                                                              x-text="item.credits_formatted"></span>
                                                        <template x-if="item.is_low_stock">
                                                            <span class="text-[11px] font-medium text-accent-700 tabular-nums"
                                                                  x-text="'Estoque: ' + item.stock"></span>
                                                        </template>
                                                    </div>
                                                </div>

                                                <form :action="item.cart_url" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="quantity" value="1">
                                                    <button type="submit"
                                                            class="w-full py-2 px-3 rounded-lg border border-line bg-paper text-ink group-hover:bg-ink group-hover:text-paper group-hover:border-ink hover:bg-slate-800 active:scale-[0.98] font-medium text-xs flex items-center justify-center gap-1.5 transition-all duration-200 whitespace-nowrap">
                                                        <x-icon name="shopping-cart" class="size-3.5 shrink-0" />
                                                        <span>Adicionar ao pedido</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                </template>
                            </div>

                            <div class="flex justify-end">
                                <a :href="tabsData[activeTab]?.category_url || '#'"
                                   class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink hover:text-accent-600 transition-colors">
                                    <span x-text="'Explorar linha completa de ' + (tabsData[activeTab]?.label || '')"></span>
                                    <x-icon name="arrow-right" class="size-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Faixa 7: Vitrine Pós-Kits #3 — Destaques & Ofertas em Estoque --}}
            @if($topDealsProducts->isNotEmpty())
                <section class="app-shell py-8 sm:py-10 space-y-5">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <x-eyebrow>Condições diferenciadas</x-eyebrow>
                            <h2 class="font-serif text-2xl sm:text-3xl text-ink mt-0.5 flex items-center gap-2">
                                <x-icon name="trending-up" class="size-6 text-money-600" />
                                <span>Destaques &amp; Ofertas em Estoque</span>
                            </h2>
                        </div>
                        <a href="{{ route('catalog.index', ['sort' => 'maior_desconto']) }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-ink hover:text-accent-600 transition-colors shrink-0">
                            <span>Ver maiores descontos</span>
                            <x-icon name="arrow-right" class="size-3.5" />
                        </a>
                    </div>

                    <div class="grid grid-cols-1 min-[440px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
                        @foreach($topDealsProducts as $dealProduct)
                            <x-product-card :product="$dealProduct" :wishlistIds="$wishlistIds" :eager="false" />
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Faixa 8: Banner Executivo Modelo A --}}
            <section class="app-shell pb-4">
                <div class="hero-dark-band hero-grid-pattern bg-ink text-paper rounded-2xl border border-slate-800 p-6 sm:p-8 lg:p-10 shadow-float flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-2.5 max-w-2xl">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-accent-400">
                            <x-icon name="credit-card" class="size-3.5" />
                            <span>5 Planos Comerciais · Modelo A 1:1</span>
                        </span>
                        <h2 class="font-serif text-2xl sm:text-3xl text-paper">
                            Previsibilidade orçamentária para cada departamento da sua empresa.
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                            Compare <strong class="text-paper">Essencial, Business, Corporate, Premium e Enterprise</strong> com conversão exata de 1 crédito = R$ 1,00 e controle de saldo por gestor.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <a href="{{ route('plans.index') }}"
                           class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-paper hover:bg-slate-200 px-5 py-3 text-xs font-semibold text-ink transition whitespace-nowrap">
                            <span>Comparar planos</span>
                            <x-icon name="arrow-right" class="size-3.5" />
                        </a>
                        <a href="{{ route('bundles.index') }}"
                           class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900/70 hover:border-paper px-4 py-3 text-xs font-medium text-paper transition whitespace-nowrap">
                            <span>Kits de Abastecimento</span>
                        </a>
                    </div>
                </div>
            </section>
        @endif

        <div x-show="mobileFiltersOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Filtrar insumos">
            <div @click="mobileFiltersOpen = false" class="fixed inset-0 bg-ink/60"></div>
            <div class="fixed inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-xl bg-surface border-t border-line p-5 shadow-float space-y-5 z-10">
                <div class="flex items-center justify-between pb-3 border-b border-line">
                    <h3 class="font-serif text-xl text-ink">Filtrar insumos</h3>
                    <button @click="mobileFiltersOpen = false" type="button" data-compact-btn aria-label="Fechar filtros" class="h-8 w-8 rounded-md flex items-center justify-center text-slate-500 hover:text-ink">
                        <x-icon name="x" class="size-4" />
                    </button>
                </div>

                <form action="{{ route('catalog.index') }}" method="GET" class="space-y-4 text-xs">
                    @if($hasSearch)
                        <input type="hidden" name="q" value="{{ request('q') }}">
                    @endif
                    @if(request('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif

                    <div class="space-y-1.5">
                        <label for="mobile_filter_category" class="font-medium text-ink block">Categoria</label>
                        <select id="mobile_filter_category" name="category" class="w-full rounded-md border-line bg-paper text-xs py-2 px-3">
                            <option value="">Todas as categorias</option>
                            @foreach($categories as $cat)
                                <optgroup label="{{ $cat->name }}">
                                    <option value="{{ $cat->slug }}" {{ $selectedCategory?->slug === $cat->slug ? 'selected' : '' }}>
                                        {{ $cat->name }} (Todos · {{ $parentCategoryCounts[$cat->id] ?? 0 }})
                                    </option>
                                    @foreach($cat->children as $child)
                                        <option value="{{ $child->slug }}" {{ $selectedCategory?->slug === $child->slug ? 'selected' : '' }}>
                                            — {{ $child->name }} ({{ $categoryProductCounts[$child->id] ?? 0 }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="mobile_min_price" class="font-medium text-ink block mb-1">Valor mín. (R$)</label>
                            <input id="mobile_min_price" type="number" name="min_price" value="{{ $effectiveMinPrice }}" min="0" step="1"
                                   class="w-full rounded-md border-line bg-paper text-xs py-2 px-2.5 tabular-nums">
                        </div>
                        <div>
                            <label for="mobile_max_price" class="font-medium text-ink block mb-1">Valor máx. (R$)</label>
                            <input id="mobile_max_price" type="number" name="max_price" value="{{ $effectiveMaxPrice }}" min="0" step="1"
                                   class="w-full rounded-md border-line bg-paper text-xs py-2 px-2.5 tabular-nums">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="mobile_min_rating" class="font-medium text-ink block">Avaliação mínima</label>
                        <select id="mobile_min_rating" name="min_rating" class="w-full rounded-md border-line bg-paper text-xs py-2 px-3">
                            <option value="" {{ ! $hasMinRating ? 'selected' : '' }}>Todas as avaliações</option>
                            <option value="4" {{ request('min_rating') == '4' ? 'selected' : '' }}>4+ estrelas</option>
                            <option value="3" {{ request('min_rating') == '3' ? 'selected' : '' }}>3+ estrelas</option>
                        </select>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="flex-1 bg-ink text-paper font-medium py-2.5 rounded-md">
                            Ver resultados
                        </button>
                        <a href="{{ route('catalog.index') }}" class="px-4 py-2.5 rounded-md border border-line bg-surface text-ink font-medium flex items-center">
                            Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
