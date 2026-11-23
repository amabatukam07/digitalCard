<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShippingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogApiController extends Controller
{
    public function products(Request $request): AnonymousResourceCollection
    {
        $query = Product::with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('stock', '>', 0);

        if ($categorySlug = trim((string) $request->input('category'))) {
            $category = Category::where('slug', $categorySlug)->firstOrFail();
            $ids = $category->children()->pluck('id')->push($category->id);
            $query->whereIn('category_id', $ids);
        }

        if ($search = trim((string) $request->input('q'))) {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($builder) use ($escaped): void {
                $builder->whereRaw("name LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                    ->orWhereRaw("description LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        if ($request->boolean('cashback')) {
            $query->where('cashback_percent', '>', 0);
        }

        if ($request->filled('min_rating')) {
            $minRating = max(1, min(5, (int) $request->input('min_rating')));
            $query->whereIn('id', function ($sub) use ($minRating): void {
                $sub->select('product_id')
                    ->from('product_reviews')
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= ?', [$minRating]);
            });
        }

        match ($request->input('sort')) {
            'menor_preco' => $query->orderBy('price')->orderBy('id'),
            'maior_preco' => $query->orderByDesc('price')->orderBy('id'),
            'maior_desconto' => $query->orderByRaw('CASE WHEN compare_at_price > price THEN ((compare_at_price - price) / compare_at_price) ELSE 0 END DESC')->orderBy('id'),
            'maior_cashback' => $query->orderByDesc('cashback_percent')->orderBy('id'),
            'melhor_avaliados' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count')->orderBy('id'),
            default => $query->orderBy('id'),
        };

        $perPage = max(1, min(50, (int) $request->input('per_page', 16)));

        return ProductResource::collection($query->paginate($perPage));
    }

    public function showProduct(Product $product): ProductResource
    {
        abort_unless($product->is_active, 404);

        $product->load('category');
        $product->loadAvg('reviews', 'rating');
        $product->loadCount('reviews');

        return new ProductResource($product);
    }

    public function categories(): AnonymousResourceCollection
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function shippingQuote(Request $request, ShippingCalculator $calculator): JsonResponse
    {
        $validated = $request->validate([
            'cep' => ['required', 'string', 'max:15'],
            'total_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $address = $calculator->lookupCep($validated['cep']);
        $quote = $calculator->calculate($validated['cep'], (int) ($validated['total_cents'] ?? 0));

        return response()->json([
            'address' => $address,
            'quote' => $quote,
        ]);
    }
}
