<?php

namespace App\Services;

use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutBuilder;

class ProductListing
{
    /** Sort keys accepted from the storefront. */
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'rating', 'popular'];

    /** Upper bound on search hits combined with filters, since Scout's database engine returns full result sets. */
    private const SEARCH_LIMIT = 500;

    /**
     * Build the storefront product query for a set of filters.
     *
     * @param  array{q?: ?string, category_id?: ?int, brand_ids?: array<int, int>, attribute_value_ids?: array<int, int>, min_price?: ?int, max_price?: ?int, min_rating?: ?int, in_stock?: bool, sort?: ?string}  $filters  Prices are in paise.
     * @return Builder<Product>
     */
    public function build(array $filters): Builder
    {
        $query = Product::query()
            ->published()
            ->with(['defaultVariant', 'brand', 'category'])
            ->select('products.*');

        $term = trim((string) ($filters['q'] ?? ''));

        if ($term !== '') {
            $query->whereIn('products.id', $this->searchIds($term));
        }

        if (! empty($filters['category_id'])) {
            $categoryIds = Category::query()->findOrFail($filters['category_id'])->selfAndDescendantIds();
            $query->whereIn('products.category_id', $categoryIds);
        }

        if (! empty($filters['brand_ids'])) {
            $query->whereIn('products.brand_id', array_map('intval', $filters['brand_ids']));
        }

        foreach ($this->valuesByAttribute($filters['attribute_value_ids'] ?? []) as $valueIds) {
            // Within one attribute the values are alternatives (OR); across attributes they must all match (AND).
            $query->where(fn (Builder $inner) => $inner
                ->whereHas('attributeValues', fn (Builder $values) => $values->whereIn('attribute_values.id', $valueIds))
                ->orWhereHas('variants', fn (Builder $variants) => $variants
                    ->whereHas('attributeValues', fn (Builder $values) => $values->whereIn('attribute_values.id', $valueIds))));
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $query->whereHas('variants', function (Builder $variants) use ($filters): void {
                $variants->where('is_active', true);

                if (isset($filters['min_price'])) {
                    $variants->where('price_paise', '>=', $filters['min_price']);
                }

                if (isset($filters['max_price'])) {
                    $variants->where('price_paise', '<=', $filters['max_price']);
                }
            });
        }

        if (! empty($filters['min_rating'])) {
            $query->where('products.rating_average', '>=', (int) $filters['min_rating']);
        }

        if (! empty($filters['in_stock'])) {
            $query->whereHas('variants', fn (Builder $variants) => $variants->where('is_active', true)->where('stock_quantity', '>', 0));
        }

        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query;
    }

    /**
     * @return array<int, int>
     */
    private function searchIds(string $term): array
    {
        /** @var ScoutBuilder $search */
        $search = Product::search($term)->query(fn (Builder $query) => $query->published());

        return $search->take(self::SEARCH_LIMIT)->get()->modelKeys();
    }

    /**
     * Group selected attribute value ids by their attribute so each attribute can be matched as a set of alternatives.
     *
     * @param  array<int, int>  $valueIds
     * @return array<int, array<int, int>>
     */
    private function valuesByAttribute(array $valueIds): array
    {
        if ($valueIds === []) {
            return [];
        }

        return AttributeValue::query()
            ->whereIn('id', array_map('intval', $valueIds))
            ->get(['id', 'attribute_id'])
            ->groupBy('attribute_id')
            ->map(fn ($values) => $values->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        $minPrice = ProductVariant::query()
            ->selectRaw('MIN(price_paise)')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('is_active', true);

        match ($sort) {
            'price_asc' => $query->selectSub($minPrice, 'min_price')->orderBy('min_price')->orderBy('products.id'),
            'price_desc' => $query->selectSub($minPrice, 'min_price')->orderByDesc('min_price')->orderBy('products.id'),
            'rating' => $query->orderByDesc('products.rating_average')->orderByDesc('products.rating_count'),
            'popular' => $query->orderByDesc('products.rating_count')->orderByDesc('products.id'),
            default => $query->orderByDesc('products.published_at')->orderByDesc('products.id'),
        };
    }
}
