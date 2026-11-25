@props([
    'product',
    'size' => 'card',
    'priority' => false,
])
@php
    $path = (string) ($product->image_path ?? '');
    $hasCustom = $path !== '' && (str_starts_with($path, 'products/') || str_starts_with($path, 'uploads/')) && \App\Support\AssetCache::fileExists(storage_path('app/public/'.ltrim($path, '/')));
    $hasStaticPath = $path !== '' && (str_starts_with($path, '/images/') || str_starts_with($path, 'images/')) && \App\Support\AssetCache::fileExists(public_path(ltrim($path, '/')));
    $hasJpg = $product->slug && \App\Support\AssetCache::fileExists(public_path('images/products/'.$product->slug.'.jpg'));
    $hasFull = $product->slug && \App\Support\AssetCache::fileExists(public_path('images/products/'.$product->slug.'.webp'));
    $hasThumb = $product->slug && \App\Support\AssetCache::fileExists(public_path('images/products/'.$product->slug.'-400.webp'));
    $exists = $hasCustom || $hasStaticPath || $hasJpg || $hasFull || $hasThumb;

    $w = $size === 'full' ? 800 : ($size === 'thumb' ? 80 : 400);
    $h = $size === 'full' ? 1000 : ($size === 'thumb' ? 100 : 500);

    $catName = $product->category?->name ?? 'Catálogo';
    $catIcon = $product->category?->icon ?: 'package';
@endphp

@if($exists)
    @php
        $mainSrc = $hasCustom
            ? asset('storage/'.ltrim($path, '/'))
            : ($hasStaticPath
                ? asset(ltrim($path, '/'))
                : ($hasJpg
                    ? asset('images/products/'.$product->slug.'.jpg')
                    : ($size === 'full' || ! $hasThumb
                        ? asset('images/products/'.$product->slug.'.webp')
                        : asset('images/products/'.$product->slug.'-400.webp'))));
        $srcsetParts = [];
        if (! $hasCustom && ! $hasStaticPath && ! $hasJpg) {
            if ($hasThumb) {
                $srcsetParts[] = asset('images/products/'.$product->slug.'-400.webp').' 400w';
            }
            if ($hasFull) {
                $srcsetParts[] = asset('images/products/'.$product->slug.'.webp').' 800w';
            }
        }
        $srcset = implode(', ', $srcsetParts);
        $aspectClass = $size === 'full' ? 'aspect-[4/5]' : 'aspect-square';
        $imgFitClass = $size === 'full' ? 'h-full w-full object-contain p-4' : ($size === 'thumb' ? 'h-full w-full object-contain p-1' : 'h-full w-full object-contain p-3 transition-transform duration-500 ease-out group-hover:scale-105');
    @endphp
    <div {{ $attributes->merge(['class' => $aspectClass.' product-photo-box w-full overflow-hidden bg-white flex items-center justify-center']) }}>
        <img src="{{ $mainSrc }}"
             @if($srcset !== '') srcset="{{ $srcset }}" sizes="{{ $size === 'full' ? '(min-width: 1024px) 50vw, 100vw' : '(min-width: 1024px) 25vw, 50vw' }}" @endif
             width="{{ $w }}"
             height="{{ $h }}"
             alt="{{ $product->name }}"
             loading="{{ $priority ? 'eager' : 'lazy' }}"
             @if($priority) fetchpriority="high" @endif
             decoding="async"
             class="{{ $imgFitClass }}">
    </div>
@else
    @php($fallbackAspect = $size === 'full' ? 'aspect-[4/5]' : 'aspect-square')
    <div {{ $attributes->merge(['class' => $fallbackAspect.' w-full overflow-hidden bg-slate-100 flex flex-col items-center justify-center gap-2 p-4 text-center select-none']) }}>
        <x-icon :name="$catIcon" class="{{ $size === 'thumb' ? 'size-5' : 'size-12' }} text-slate-400" />
        @if($size !== 'thumb')
            <span class="text-xs text-slate-500 line-clamp-1">{{ $catName }}</span>
        @endif
    </div>
@endif
