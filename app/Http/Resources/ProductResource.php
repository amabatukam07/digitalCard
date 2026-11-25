<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'sku' => $this->sku,
            'brand' => $this->brand,
            'unit' => $this->unit,
            'presentation' => $this->presentation,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'credits' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'discount_percent' => (int) $this->discount_percent,
            'stock' => (int) $this->stock,
            'min_stock' => (int) $this->min_stock,
            'image_url' => $this->image_url,
            'thumbnail_url' => $this->thumbnail_url,
            'gallery' => $this->gallerySlides(),
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
