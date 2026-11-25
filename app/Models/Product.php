<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'barcode',
        'brand',
        'unit',
        'presentation',
        'name',
        'slug',
        'description',
        'cost_price',
        'inbound_freight',
        'other_costs',
        'markup_percent',
        'das_rate',
        'price',
        'compare_at_price',
        'stock',
        'min_stock',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'inbound_freight' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'das_rate' => 'decimal:2',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->orderByDesc('effective_at')->orderByDesc('id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->withPivot(['supplier_sku', 'supplier_cost', 'lead_time_days', 'last_quoted_at'])
            ->withTimestamps();
    }

    public function getTotalUnitCostAttribute(): float
    {
        return round((float) $this->cost_price + (float) $this->inbound_freight + (float) $this->other_costs, 2);
    }

    public function getEstimatedDasAmountAttribute(): float
    {
        return round((float) $this->price * ((float) $this->das_rate / 100.0), 2);
    }

    public function getEstimatedResultAfterDasAttribute(): float
    {
        return round((float) $this->price - $this->total_unit_cost - $this->estimated_das_amount, 2);
    }

    public function getEstimatedMarginPercentAttribute(): float
    {
        $sale = (float) $this->price;
        if ($sale <= 0.0) {
            return 0.0;
        }

        return round(($this->estimated_result_after_das / $sale) * 100.0, 2);
    }

    public function getIsLowStockAttribute(): bool
    {
        return (int) $this->stock <= (int) $this->min_stock;
    }

    public function getImageUrlAttribute(): string
    {
        $path = (string) ($this->image_path ?? '');
        if ($path !== '') {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            if (str_starts_with($path, 'products/') || str_starts_with($path, 'uploads/')) {
                return asset('storage/'.ltrim($path, '/'));
            }
            if (str_starts_with($path, '/images/') || str_starts_with($path, 'images/')) {
                return asset(ltrim($path, '/'));
            }
        }

        $slug = $this->slug ?: 'produto';
        if (file_exists(public_path('images/products/'.$slug.'.jpg'))) {
            return asset('images/products/'.$slug.'.jpg');
        }
        if (file_exists(public_path('images/products/'.$slug.'.png'))) {
            return asset('images/products/'.$slug.'.png');
        }

        return asset('images/products/'.$slug.'.webp');
    }

    public function getThumbnailUrlAttribute(): string
    {
        $path = (string) ($this->image_path ?? '');
        if ($path !== '' && (str_starts_with($path, 'products/') || str_starts_with($path, 'uploads/'))) {
            return asset('storage/'.ltrim($path, '/'));
        }

        $slug = $this->slug ?: 'produto';
        if (file_exists(public_path('images/products/'.$slug.'-400.webp'))) {
            return asset('images/products/'.$slug.'-400.webp');
        }
        if (file_exists(public_path('images/products/'.$slug.'-400.jpg'))) {
            return asset('images/products/'.$slug.'-400.jpg');
        }

        return $this->image_url;
    }

    public function hasImageFile(): bool
    {
        $path = (string) ($this->image_path ?? '');
        if ($path !== '' && (str_starts_with($path, 'products/') || str_starts_with($path, 'uploads/'))) {
            return file_exists(storage_path('app/public/'.ltrim($path, '/')));
        }
        if ($path !== '' && (str_starts_with($path, '/images/') || str_starts_with($path, 'images/'))) {
            return file_exists(public_path(ltrim($path, '/')));
        }

        return (bool) ($this->slug && (
            file_exists(public_path('images/products/'.$this->slug.'.webp'))
            || file_exists(public_path('images/products/'.$this->slug.'.jpg'))
            || file_exists(public_path('images/products/'.$this->slug.'.png'))
        ));
    }

    /**
     * Retorna os slides da galeria do produto garantindo ao menos 3 imagens.
     *
     * @return list<array{url: string, alt: string, caption: string}>
     */
    public function gallerySlides(): array
    {
        $gallery = $this->relationLoaded('images')
            ? $this->images
            : $this->images()->get();

        $slides = $gallery->map(fn (ProductImage $img): array => [
            'url' => $img->url,
            'alt' => (string) ($img->alt ?: $this->name),
            'caption' => (string) ($img->caption ?: 'Vista do produto'),
        ])->values()->all();

        if (count($slides) >= 3) {
            return $slides;
        }

        $fallbackUrl = $this->image_url;
        $fallbackCaptions = ['Vista principal', 'Detalhe e acabamento', 'Apresentação comercial'];
        $existingCaptions = array_column($slides, 'caption');

        foreach ($fallbackCaptions as $caption) {
            if (count($slides) >= 3) {
                break;
            }
            if (! in_array($caption, $existingCaptions, true)) {
                $slides[] = [
                    'url' => $fallbackUrl,
                    'alt' => $this->name.' — '.$caption,
                    'caption' => $caption,
                ];
            }
        }

        while (count($slides) < 3) {
            $slides[] = [
                'url' => $fallbackUrl,
                'alt' => $this->name,
                'caption' => 'Imagem '.(count($slides) + 1),
            ];
        }

        return $slides;
    }

    public function getDiscountPercentAttribute(): int
    {
        if ($this->compare_at_price && (float) $this->compare_at_price > (float) $this->price) {
            return (int) round((1 - ((float) $this->price / (float) $this->compare_at_price)) * 100);
        }

        return 0;
    }

    public function getOriginalPriceAttribute(): float
    {
        if ($this->compare_at_price && (float) $this->compare_at_price > (float) $this->price) {
            return (float) $this->compare_at_price;
        }

        return (float) $this->price;
    }

    /**
     * @return array{rating: float, count: int}
     */
    public function recalculateRating(): array
    {
        $this->loadAvg('reviews', 'rating')->loadCount('reviews');

        return ['rating' => $this->rating, 'count' => $this->reviews_count];
    }

    public function getRatingAttribute(): float
    {
        if (array_key_exists('reviews_avg_rating', $this->attributes)) {
            return $this->attributes['reviews_avg_rating'] !== null
                ? round((float) $this->attributes['reviews_avg_rating'], 1)
                : 0.0;
        }

        if ($this->relationLoaded('reviews')) {
            return $this->reviews->isNotEmpty()
                ? round((float) $this->reviews->avg('rating'), 1)
                : 0.0;
        }

        $avg = $this->reviews()->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : 0.0;
    }

    public function getReviewsCountAttribute(): int
    {
        if (array_key_exists('reviews_count', $this->attributes)) {
            return (int) $this->attributes['reviews_count'];
        }

        if ($this->relationLoaded('reviews')) {
            return $this->reviews->count();
        }

        return (int) $this->reviews()->count();
    }
}
