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
            'sku' => 'PROD-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'discount_percent' => (int) $this->discount_percent,
            'cashback_percent' => (float) $this->cashback_percent,
            'stock' => (int) $this->stock,
            'image_url' => $this->image_url,
            'thumbnail_url' => $this->thumbnail_url,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
