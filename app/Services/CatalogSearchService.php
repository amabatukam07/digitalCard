<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogSearchService
{
    /**
     * Normaliza limites de preço ignorando entradas vazias e invertendo automaticamente quando min > max.
     *
     * @return array{min: float|null, max: float|null}
     */
    public function normalizePriceBounds(mixed $minRaw, mixed $maxRaw): array
    {
        $min = $this->parseNonNegativePrice($minRaw);
        $max = $this->parseNonNegativePrice($maxRaw);

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [
            'min' => $min,
            'max' => $max,
        ];
    }

    /**
     * Tokeniza a consulta do usuário em até 6 termos únicos sem espaços redundantes.
     *
     * @return list<string>
     */
    public function tokenize(string $rawSearch): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $rawSearch) ?? '');
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $unique = [];

        foreach ($parts as $part) {
            $lower = mb_strtolower($part, 'UTF-8');
            if (! array_key_exists($lower, $unique)) {
                $unique[$lower] = $part;
            }

            if (count($unique) >= 6) {
                break;
            }
        }

        return array_values($unique);
    }

    /**
     * Escapa curingas LIKE (% e _) mantendo compatibilidade com SQLite e MySQL.
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Aplica busca multi-termo com interseção (AND) nos campos principais e nome da categoria.
     *
     * @param  Builder<Product>  $query
     * @return list<string>
     */
    public function applySearch(Builder $query, string $rawSearch): array
    {
        $tokens = $this->tokenize($rawSearch);
        if ($tokens === []) {
            return [];
        }

        foreach ($tokens as $token) {
            $like = '%'.$this->escapeLike($token).'%';

            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('presentation', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($like): void {
                        $categoryQuery->where('name', 'like', $like);
                    });
            });
        }

        return $tokens;
    }

    /**
     * Ordena resultados de busca por relevância determinística (SKU > prefixo > nome > marca > descrição).
     *
     * @param  Builder<Product>  $query
     */
    public function applyRelevanceOrder(Builder $query, string $rawSearch): void
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $rawSearch) ?? ''), 'UTF-8');
        if ($normalized === '') {
            $query->orderBy('id');

            return;
        }

        $escaped = $this->escapeLike($normalized);
        $prefixLike = $escaped.'%';
        $containsLike = '%'.$escaped.'%';

        $scoreSql = "(CASE WHEN LOWER(COALESCE(sku, '')) = ? THEN 120 ELSE 0 END"
            .' + CASE WHEN LOWER(name) LIKE ? THEN 70 ELSE 0 END'
            .' + CASE WHEN LOWER(name) LIKE ? THEN 45 ELSE 0 END'
            ." + CASE WHEN LOWER(COALESCE(brand, '')) LIKE ? THEN 25 ELSE 0 END"
            ." + CASE WHEN LOWER(COALESCE(description, '')) LIKE ? THEN 10 ELSE 0 END) DESC";

        $query->orderByRaw($scoreSql, [
            $normalized,
            $prefixLike,
            $containsLike,
            $containsLike,
            $containsLike,
        ])
            ->orderByDesc('reviews_avg_rating')
            ->orderBy('id');
    }

    /**
     * Seleciona produtos relacionados em 3 níveis: mesma subcategoria, subcategorias irmãs e faixa de preço próxima.
     *
     * @return Collection<int, Product>
     */
    public function relatedProducts(Product $product, int $limit = 4): Collection
    {
        $targetPrice = (float) $product->price;
        $excludeIds = [$product->id];

        $level1 = $this->baseRelatedQuery($excludeIds, $targetPrice)
            ->where('category_id', $product->category_id)
            ->limit($limit)
            ->get();

        $collected = $level1;

        if ($collected->count() < $limit && $product->category?->parent_id) {
            $siblingCategoryIds = Category::query()
                ->where('parent_id', $product->category->parent_id)
                ->where('id', '!=', $product->category_id)
                ->pluck('id');

            if ($siblingCategoryIds->isNotEmpty()) {
                $excludeIds = array_merge($excludeIds, $collected->pluck('id')->all());
                $level2 = $this->baseRelatedQuery($excludeIds, $targetPrice)
                    ->whereIn('category_id', $siblingCategoryIds)
                    ->limit($limit - $collected->count())
                    ->get();

                $collected = $collected->concat($level2);
            }
        }

        if ($collected->count() < $limit) {
            $excludeIds = array_merge($excludeIds, $collected->pluck('id')->all());
            $level3 = $this->baseRelatedQuery($excludeIds, $targetPrice)
                ->limit($limit - $collected->count())
                ->get();

            $collected = $collected->concat($level3);
        }

        return $collected->values();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return Builder<Product>
     */
    private function baseRelatedQuery(array $excludeIds, float $targetPrice): Builder
    {
        return Product::with(['category', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereNotIn('id', $excludeIds)
            ->orderByRaw('ABS(price - ?) ASC', [$targetPrice])
            ->orderBy('id');
    }

    private function parseNonNegativePrice(mixed $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim((string) $raw);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return round(max(0.0, (float) $trimmed), 2);
    }

    /**
     * Intercala produtos em round-robin entre todas as categorias principais e suas subcategorias.
     *
     * @return list<int>
     */
    public function interleavedProductIds(): array
    {
        return Cache::remember('catalog_interleaved_ids', 300, function (): array {
            $rows = Product::query()
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->where('products.is_active', true)
                ->where('products.stock', '>', 0)
                ->orderBy('products.id')
                ->get([
                    'products.id as id',
                    'products.category_id as category_id',
                    'categories.parent_id as parent_id',
                ]);

            /** @var array<int, array<int, list<int>>> $byParent */
            $byParent = [];
            foreach ($rows as $row) {
                $parentId = (int) ($row->parent_id ?: $row->category_id);
                $subCategoryId = (int) $row->category_id;
                $byParent[$parentId][$subCategoryId][] = (int) $row->id;
            }

            if ($byParent === []) {
                return [];
            }

            ksort($byParent);

            /** @var array<int, list<int>> $parentQueues */
            $parentQueues = [];
            foreach ($byParent as $parentId => $subBuckets) {
                ksort($subBuckets);
                $queue = [];
                do {
                    $added = false;
                    foreach ($subBuckets as &$bucket) {
                        if ($bucket !== []) {
                            $queue[] = array_shift($bucket);
                            $added = true;
                        }
                    }
                    unset($bucket);
                } while ($added);

                $parentQueues[$parentId] = $queue;
            }

            $interleaved = [];
            do {
                $added = false;
                foreach ($parentQueues as &$queue) {
                    if ($queue !== []) {
                        $interleaved[] = array_shift($queue);
                        $added = true;
                    }
                }
                unset($queue);
            } while ($added);

            return $interleaved;
        });
    }

    /**
     * Ordena a consulta segundo o sequenciamento round-robin multi-categoria.
     *
     * @param  Builder<Product>  $query
     */
    public function applyInterleavedOrder(Builder $query): void
    {
        $orderedIds = $this->interleavedProductIds();
        if ($orderedIds === []) {
            $query->orderBy('products.id');

            return;
        }

        $cases = [];
        foreach ($orderedIds as $position => $productId) {
            $cases[] = 'WHEN '.((int) $productId).' THEN '.((int) $position);
        }

        $caseSql = 'CASE products.id '.implode(' ', $cases).' ELSE '.count($orderedIds).' END ASC';
        $query->orderByRaw($caseSql)->orderBy('products.id');
    }

    /**
     * Monta em lote as vitrines dinâmicas exibidas após os kits na home, evitando repetição da 1ª página e queries N+1.
     *
     * @param  list<int>  $excludeIds
     * @param  Collection<int, Category>  $rootCategories
     * @return array{
     *     postKitDiscoveryProducts: Collection<int, Product>,
     *     departmentShowcaseTabs: list<array{key: string, label: string, slug: string|null, icon: string, count: int, products: Collection<int, Product>}>,
     *     topDealsProducts: Collection<int, Product>
     * }
     */
    public function buildHomeShowcases(array $excludeIds, Collection $rootCategories): array
    {
        $excludeMap = array_flip(array_map('intval', $excludeIds));
        $interleavedIds = $this->interleavedProductIds();

        $metaRows = Product::query()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_active', true)
            ->where('products.stock', '>', 0)
            ->get([
                'products.id as id',
                'products.category_id as category_id',
                'categories.parent_id as parent_id',
                'products.price as price',
                'products.compare_at_price as compare_at_price',
            ]);

        /** @var array<int, int> $rootIdByProduct */
        $rootIdByProduct = [];
        /** @var array<int, bool> $dealFlagByProduct */
        $dealFlagByProduct = [];
        /** @var array<int, int> $rootCounts */
        $rootCounts = [];

        foreach ($metaRows as $row) {
            $pid = (int) $row->id;
            $rootId = (int) ($row->parent_id ?: $row->category_id);
            $rootIdByProduct[$pid] = $rootId;
            $dealFlagByProduct[$pid] = $row->compare_at_price !== null && (float) $row->compare_at_price > (float) $row->price;
            $rootCounts[$rootId] = ($rootCounts[$rootId] ?? 0) + 1;
        }

        // Vitrine 1: 12 produtos intercalados distintos dos já exibidos na página atual
        $discoveryIds = [];
        foreach ($interleavedIds as $pid) {
            if (isset($excludeMap[$pid])) {
                continue;
            }
            $discoveryIds[] = $pid;
            if (count($discoveryIds) >= 12) {
                break;
            }
        }
        $discoveryMap = array_flip($discoveryIds);

        // Vitrine 2: abas por departamento ('all' + 6 categorias principais)
        $tabIdMap = [];
        $allTabIds = [];
        foreach ($interleavedIds as $pid) {
            if (isset($excludeMap[$pid]) || isset($discoveryMap[$pid])) {
                continue;
            }
            $allTabIds[] = $pid;
            if (count($allTabIds) >= 4) {
                break;
            }
        }
        $tabIdMap['all'] = $allTabIds;

        foreach ($rootCategories as $rootCat) {
            $rootId = (int) $rootCat->id;
            $catIds = [];

            foreach ($interleavedIds as $pid) {
                if (($rootIdByProduct[$pid] ?? null) !== $rootId || isset($excludeMap[$pid])) {
                    continue;
                }
                $catIds[] = $pid;
                if (count($catIds) >= 4) {
                    break;
                }
            }

            if (count($catIds) < 4) {
                foreach ($interleavedIds as $pid) {
                    if (($rootIdByProduct[$pid] ?? null) !== $rootId || in_array($pid, $catIds, true)) {
                        continue;
                    }
                    $catIds[] = $pid;
                    if (count($catIds) >= 4) {
                        break;
                    }
                }
            }

            $tabIdMap[(string) $rootCat->slug] = $catIds;
        }

        // Vitrine 3: até 4 ofertas ou destaques melhor avaliados intercalados entre categorias
        $dealsIds = [];
        foreach ($interleavedIds as $pid) {
            if (! isset($excludeMap[$pid]) && ($dealFlagByProduct[$pid] ?? false)) {
                $dealsIds[] = $pid;
                if (count($dealsIds) >= 4) {
                    break;
                }
            }
        }

        if (count($dealsIds) < 4) {
            $topRatedRows = Product::query()
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->withAvg('reviews', 'rating')
                ->where('products.is_active', true)
                ->where('products.stock', '>', 0)
                ->whereNotIn('products.id', array_values(array_unique(array_merge(array_keys($excludeMap), $dealsIds))))
                ->orderByDesc('reviews_avg_rating')
                ->orderBy('products.id')
                ->limit(36)
                ->get([
                    'products.id as id',
                    'products.category_id as category_id',
                    'categories.parent_id as parent_id',
                ]);

            /** @var array<int, list<int>> $ratedBuckets */
            $ratedBuckets = [];
            foreach ($topRatedRows as $row) {
                $rootId = (int) ($row->parent_id ?: $row->category_id);
                $ratedBuckets[$rootId][] = (int) $row->id;
            }
            ksort($ratedBuckets);

            while (count($dealsIds) < 4) {
                $added = false;
                foreach ($ratedBuckets as &$bucket) {
                    if ($bucket !== []) {
                        $dealsIds[] = array_shift($bucket);
                        $added = true;
                        if (count($dealsIds) >= 4) {
                            break;
                        }
                    }
                }
                unset($bucket);
                if (! $added) {
                    break;
                }
            }
        }

        $allShowcaseIds = array_values(array_unique(array_merge(
            $discoveryIds,
            $dealsIds,
            ...array_values($tabIdMap),
        )));

        $hydrated = $allShowcaseIds === []
            ? collect()
            : Product::with(['category', 'images'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->whereIn('id', $allShowcaseIds)
                ->get()
                ->keyBy('id');

        $hydrateList = static fn (array $ids): Collection => collect($ids)
            ->map(static fn (int $id): ?Product => $hydrated->get($id))
            ->filter()
            ->values();

        $departmentShowcaseTabs = [
            [
                'key' => 'all',
                'label' => 'Mix Multi-Setor',
                'slug' => null,
                'icon' => 'sparkles',
                'count' => count($interleavedIds),
                'products' => $hydrateList($tabIdMap['all']),
            ],
        ];

        foreach ($rootCategories as $rootCat) {
            $departmentShowcaseTabs[] = [
                'key' => (string) $rootCat->slug,
                'label' => (string) $rootCat->name,
                'slug' => (string) $rootCat->slug,
                'icon' => (string) ($rootCat->icon ?: 'package'),
                'count' => (int) ($rootCounts[(int) $rootCat->id] ?? 0),
                'products' => $hydrateList($tabIdMap[(string) $rootCat->slug] ?? []),
            ];
        }

        return [
            'postKitDiscoveryProducts' => $hydrateList($discoveryIds),
            'departmentShowcaseTabs' => $departmentShowcaseTabs,
            'topDealsProducts' => $hydrateList($dealsIds),
        ];
    }
}
