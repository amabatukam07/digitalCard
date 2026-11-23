<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompareController extends Controller
{
    public const SESSION_KEY = 'compare_product_ids';

    public const MAX_ITEMS = 4;

    public function index(Request $request): View
    {
        $ids = collect($request->session()->get(self::SESSION_KEY, []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(self::MAX_ITEMS)
            ->values();

        $productsById = Product::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $products = $ids->map(fn (int $id): ?Product => $productsById->get($id))->filter()->values();

        if ($products->count() !== $ids->count()) {
            $request->session()->put(self::SESSION_KEY, $products->pluck('id')->all());
        }

        $lowestPrice = $products->min('price');
        $highestCashback = $products->max('cashback_percent');
        $highestRating = $products->max(fn (Product $p): float => (float) ($p->reviews_avg_rating ?? 0));

        $suggestions = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->when($products->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $products->pluck('id')))
            ->orderByDesc('cashback_percent')
            ->limit(4)
            ->get();

        return view('catalog.compare', compact(
            'products',
            'lowestPrice',
            'highestCashback',
            'highestRating',
            'suggestions',
        ));
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $ids = collect($request->session()->get(self::SESSION_KEY, []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (in_array($product->id, $ids, true)) {
            $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== $product->id));
            $request->session()->put(self::SESSION_KEY, $ids);

            return back()->with('success', "{$product->name} removido do comparador.");
        }

        if (count($ids) >= self::MAX_ITEMS) {
            return back()->with('error', 'O comparador aceita no máximo '.self::MAX_ITEMS.' produtos por vez. Remova um item para adicionar outro.');
        }

        $ids[] = $product->id;
        $request->session()->put(self::SESSION_KEY, $ids);

        return back()->with('success', "{$product->name} adicionado ao comparador ({$this->countLabel(count($ids))}).");
    }

    public function remove(Request $request, Product $product): RedirectResponse
    {
        $ids = collect($request->session()->get(self::SESSION_KEY, []))
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $product->id)
            ->values()
            ->all();

        $request->session()->put(self::SESSION_KEY, $ids);

        return back()->with('success', "{$product->name} removido do comparador.");
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return back()->with('success', 'Comparador limpo com sucesso.');
    }

    private function countLabel(int $count): string
    {
        return $count.'/'.self::MAX_ITEMS;
    }
}
