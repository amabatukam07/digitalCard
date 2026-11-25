@props([
    'product',
    'mode' => 'card',
    'priority' => false,
    'href' => null,
])

@php
    $slides = $product->gallerySlides();
    $slideCount = count($slides);
@endphp

@if($mode === 'detail')
    <div x-data="{
            idx: 0,
            total: {{ $slideCount }},
            lightbox: false,
            touchStartX: 0,
            touchStartY: 0,
            prev() {
                this.idx = (this.idx - 1 + this.total) % this.total;
            },
            next() {
                this.idx = (this.idx + 1) % this.total;
            },
            go(i) {
                this.idx = i;
            },
            shouldHandleKey(e) {
                const tag = e.target?.tagName;
                return tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT' && !e.target?.isContentEditable;
            },
            onTouchStart(e) {
                this.touchStartX = e.changedTouches[0].clientX;
                this.touchStartY = e.changedTouches[0].clientY;
            },
            onTouchEnd(e) {
                const dx = e.changedTouches[0].clientX - this.touchStartX;
                const dy = e.changedTouches[0].clientY - this.touchStartY;
                if (Math.abs(dx) > 36 && Math.abs(dx) > Math.abs(dy)) {
                    if (dx < 0) this.next();
                    else this.prev();
                }
            }
         }"
         @keydown.arrow-left.window="if (shouldHandleKey($event)) prev()"
         @keydown.arrow-right.window="if (shouldHandleKey($event)) next()"
         @keydown.escape.window="lightbox = false"
         class="space-y-3">

        <div class="relative rounded-xl border border-line bg-white product-photo-box overflow-hidden group"
             @touchstart.passive="onTouchStart($event)"
             @touchend.passive="onTouchEnd($event)">
            <div class="relative aspect-square sm:aspect-[4/3] w-full flex items-center justify-center bg-white product-photo-box">
                @foreach($slides as $slideIndex => $slide)
                    <img x-show="idx === {{ $slideIndex }}"
                         @if($slideIndex > 0) x-cloak @endif
                         x-transition:enter="transition-opacity duration-700 ease-out"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         src="{{ $slide['url'] }}"
                         alt="{{ $slide['alt'] }}"
                         loading="{{ $priority && $slideIndex === 0 ? 'eager' : 'lazy' }}"
                         @click="lightbox = true"
                         class="h-full w-full object-contain p-4 sm:p-6 cursor-zoom-in transition-opacity duration-700 select-none">
                @endforeach
            </div>

            <button type="button"
                    data-compact-btn
                    @click.prevent="prev()"
                    aria-label="Imagem anterior"
                    class="absolute left-3 top-1/2 -translate-y-1/2 h-9 w-9 rounded-full bg-surface/95 border border-line text-ink hover:border-ink hover:bg-ink hover:text-paper shadow-sm flex items-center justify-center transition">
                <x-icon name="arrow-left" class="size-4" />
            </button>

            <button type="button"
                    data-compact-btn
                    @click.prevent="next()"
                    aria-label="Próxima imagem"
                    class="absolute right-3 top-1/2 -translate-y-1/2 h-9 w-9 rounded-full bg-surface/95 border border-line text-ink hover:border-ink hover:bg-ink hover:text-paper shadow-sm flex items-center justify-center transition">
                <x-icon name="arrow-right" class="size-4" />
            </button>

            <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between gap-2 pointer-events-none">
                <span class="px-2.5 py-1 rounded-md bg-ink/85 text-paper text-[11px] font-medium">
                    @foreach($slides as $slideIndex => $slide)
                        <span x-show="idx === {{ $slideIndex }}" @if($slideIndex > 0) x-cloak @endif>{{ $slide['caption'] }}</span>
                    @endforeach
                </span>

                <span class="px-2.5 py-1 rounded-md bg-surface/95 border border-line text-ink font-mono text-[11px] font-semibold tabular-nums">
                    <span x-text="idx + 1">1</span> / {{ $slideCount }}
                </span>
            </div>
        </div>

        {{-- Faixa de miniaturas clicáveis --}}
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2.5" role="tablist" aria-label="Galeria de fotos do produto">
            @foreach($slides as $slideIndex => $slide)
                <button type="button"
                        data-compact-btn
                        @click="go({{ $slideIndex }})"
                        role="tab"
                        :aria-selected="idx === {{ $slideIndex }} ? 'true' : 'false'"
                        :class="idx === {{ $slideIndex }} ? 'border-ink ring-2 ring-ink/20' : 'border-line hover:border-slate-400 opacity-80 hover:opacity-100'"
                        class="relative aspect-square rounded-lg border bg-white product-photo-box overflow-hidden p-1.5 transition">
                    <img src="{{ $slide['url'] }}"
                         alt="{{ $slide['alt'] }}"
                         loading="lazy"
                         class="h-full w-full object-contain">
                    <span class="sr-only">{{ $slide['caption'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Lightbox Fullscreen --}}
        <div x-show="lightbox"
             x-cloak
             x-transition
             @click.self="lightbox = false"
             role="dialog"
             aria-modal="true"
             aria-label="Visualização ampliada de imagens"
             class="fixed inset-0 z-50 bg-slate-950/90 flex items-center justify-center p-4 sm:p-8">
            <button type="button"
                    data-compact-btn
                    @click="lightbox = false"
                    aria-label="Fechar ampliação"
                    class="absolute top-4 right-4 h-10 w-10 rounded-full bg-white text-ink hover:bg-slate-200 flex items-center justify-center shadow-lg">
                <x-icon name="x" class="size-5" />
            </button>

            <button type="button"
                    data-compact-btn
                    @click="prev()"
                    aria-label="Foto anterior"
                    class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-white/95 text-ink hover:bg-white flex items-center justify-center shadow-lg">
                <x-icon name="arrow-left" class="size-5" />
            </button>

            <div class="max-w-3xl w-full bg-white product-photo-box rounded-2xl p-4 sm:p-6 shadow-2xl space-y-3">
                <div class="aspect-square max-h-[72vh] mx-auto flex items-center justify-center">
                    @foreach($slides as $slideIndex => $slide)
                        <img x-show="idx === {{ $slideIndex }}"
                             @if($slideIndex > 0) x-cloak @endif
                             src="{{ $slide['url'] }}"
                             alt="{{ $slide['alt'] }}"
                             class="max-h-full max-w-full object-contain">
                    @endforeach
                </div>
                <div class="flex items-center justify-between text-xs text-slate-600 border-t border-line pt-2.5">
                    @foreach($slides as $slideIndex => $slide)
                        <span x-show="idx === {{ $slideIndex }}" @if($slideIndex > 0) x-cloak @endif class="font-medium text-ink truncate pr-2">{{ $slide['alt'] }}</span>
                    @endforeach
                    <span class="font-mono font-semibold tabular-nums shrink-0"><span x-text="idx + 1">1</span> / {{ $slideCount }}</span>
                </div>
            </div>

            <button type="button"
                    data-compact-btn
                    @click="next()"
                    aria-label="Próxima foto"
                    class="absolute right-3 sm:right-4 top-1/2 -translate-y-1/2 h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-white/95 text-ink hover:bg-white flex items-center justify-center shadow-lg">
                <x-icon name="arrow-right" class="size-5" />
            </button>
        </div>
    </div>
@else
    <div x-data="{
            idx: 0,
            total: {{ $slideCount }},
            touchStartX: 0,
            touchStartY: 0,
            prev() {
                this.idx = (this.idx - 1 + this.total) % this.total;
            },
            next() {
                this.idx = (this.idx + 1) % this.total;
            },
            go(i) {
                this.idx = i;
            },
            onTouchStart(e) {
                this.touchStartX = e.changedTouches[0].clientX;
                this.touchStartY = e.changedTouches[0].clientY;
            },
            onTouchEnd(e) {
                const dx = e.changedTouches[0].clientX - this.touchStartX;
                const dy = e.changedTouches[0].clientY - this.touchStartY;
                if (Math.abs(dx) > 30 && Math.abs(dx) > Math.abs(dy)) {
                    if (dx < 0) this.next();
                    else this.prev();
                }
            }
         }"
         @touchstart.passive="onTouchStart($event)"
         @touchend.passive="onTouchEnd($event)"
         class="relative aspect-square w-full bg-white product-photo-box overflow-hidden select-none">

        @if($href)
            <a href="{{ $href }}" class="block h-full w-full">
                @foreach($slides as $slideIndex => $slide)
                    <img x-show="idx === {{ $slideIndex }}"
                         @if($slideIndex > 0) x-cloak @endif
                         x-transition:enter="transition-opacity duration-700 ease-out"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         src="{{ $slide['url'] }}"
                         alt="{{ $slide['alt'] }}"
                         width="400"
                         height="400"
                         loading="{{ $priority && $slideIndex === 0 ? 'eager' : 'lazy' }}"
                         decoding="async"
                         class="h-full w-full object-contain p-3 transition-transform duration-700 ease-out group-hover:scale-105">
                @endforeach
            </a>
        @else
            @foreach($slides as $slideIndex => $slide)
                <img x-show="idx === {{ $slideIndex }}"
                     @if($slideIndex > 0) x-cloak @endif
                     x-transition:enter="transition-opacity duration-700 ease-out"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     src="{{ $slide['url'] }}"
                     alt="{{ $slide['alt'] }}"
                     width="400"
                     height="400"
                     loading="{{ $priority && $slideIndex === 0 ? 'eager' : 'lazy' }}"
                     decoding="async"
                     class="h-full w-full object-contain p-3">
            @endforeach
        @endif

        <button type="button"
                data-compact-btn
                @click.prevent.stop="prev()"
                aria-label="Foto anterior de {{ $product->name }}"
                class="carousel-prev-btn absolute left-2 top-1/2 -translate-y-1/2 z-10 h-7 w-7 rounded-full bg-surface/90 border border-line text-ink hover:bg-ink hover:text-paper shadow-sm flex items-center justify-center opacity-85 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 transition">
            <x-icon name="arrow-left" class="size-3.5" />
        </button>

        <button type="button"
                data-compact-btn
                @click.prevent.stop="next()"
                aria-label="Próxima foto de {{ $product->name }}"
                class="carousel-next-btn absolute right-2 top-1/2 -translate-y-1/2 z-10 h-7 w-7 rounded-full bg-surface/90 border border-line text-ink hover:bg-ink hover:text-paper shadow-sm flex items-center justify-center opacity-85 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 transition">
            <x-icon name="arrow-right" class="size-3.5" />
        </button>

        <div class="absolute bottom-2 inset-x-2 z-10 flex items-center justify-between pointer-events-none">
            <div class="flex items-center gap-1 rounded-full bg-surface/90 border border-line px-2 py-1 pointer-events-auto shadow-sm">
                @foreach($slides as $slideIndex => $slide)
                    <button type="button"
                            data-compact-btn
                            @click.prevent.stop="go({{ $slideIndex }})"
                            aria-label="Ver imagem {{ $slideIndex + 1 }}"
                            :class="idx === {{ $slideIndex }} ? 'w-3.5 bg-ink' : 'w-1.5 bg-slate-300 hover:bg-slate-500'"
                            class="h-1.5 rounded-full transition-all"></button>
                @endforeach
            </div>

            <span class="rounded-md bg-ink/80 text-paper font-mono text-[10px] font-semibold px-1.5 py-0.5 tabular-nums">
                <span x-text="idx + 1">1</span>/{{ $slideCount }}
            </span>
        </div>
    </div>
@endif
