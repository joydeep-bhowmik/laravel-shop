<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Banner;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class StorefrontCatalog
{
    private const HOME_TTL_SECONDS = 300;

    private const RECENT_SESSION_KEY = 'store.recently_viewed';

    private const RECENT_LIMIT = 8;

    /**
     * Top-level categories with their active children, used by the header navigation.
     *
     * Not cached: two small indexed queries, and it avoids storing model objects in the cache.
     *
     * @return Collection<int, Category>
     */
    public function navigation(): Collection
    {
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->active()])
            ->orderBy('position')
            ->get();
    }

    /**
     * Everything the home page shows.
     *
     * Only product, banner and category ids are cached, then models are reloaded in order. This keeps model
     * objects out of the cache store and means edits show up as soon as the short TTL passes.
     *
     * @return array{banners: Collection<int, Banner>, categories: Collection<int, Category>, newArrivals: Collection<int, Product>, bestSellers: Collection<int, Product>, deals: Collection<int, Product>}
     */
    public function home(): array
    {
        $ids = Cache::remember('store.home.ids', self::HOME_TTL_SECONDS, fn (): array => [
            'banners' => Banner::query()->live()->pluck('id')->all(),
            'categories' => Category::query()->active()->whereNull('parent_id')->orderBy('position')->limit(6)->pluck('id')->all(),
            'newArrivals' => Product::query()->published()->latest('published_at')->limit(8)->pluck('id')->all(),
            'bestSellers' => $this->bestSellerIds(8),
            'deals' => Product::query()->published()->onDeal()->orderBy('deal_ends_at')->limit(4)->pluck('id')->all(),
        ]);

        return [
            'banners' => $this->loadInOrder(Banner::class, $ids['banners'], ['media']),
            'categories' => $this->loadInOrder(Category::class, $ids['categories']),
            'newArrivals' => $this->loadInOrder(Product::class, $ids['newArrivals'], ['defaultVariant', 'brand']),
            'bestSellers' => $this->loadInOrder(Product::class, $ids['bestSellers'], ['defaultVariant', 'brand']),
            'deals' => $this->loadInOrder(Product::class, $ids['deals'], ['defaultVariant', 'brand']),
        ];
    }

    /**
     * Products ranked by units sold in paid orders, falling back to review count for a cold catalog.
     *
     * @return Collection<int, Product>
     */
    public function bestSellers(int $limit): Collection
    {
        return $this->loadInOrder(Product::class, $this->bestSellerIds($limit), ['defaultVariant', 'brand']);
    }

    /**
     * @return array<int, int>
     */
    private function bestSellerIds(int $limit): array
    {
        $soldIds = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered])
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->groupBy('product_variants.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit($limit)
            ->pluck('product_variants.product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($soldIds !== []) {
            return Product::query()->published()->whereIn('id', $soldIds)->pluck('id')
                ->sortBy(fn (int $id): int => array_search($id, $soldIds, true))
                ->values()
                ->all();
        }

        return Product::query()->published()->orderByDesc('rating_count')->limit($limit)->pluck('id')->all();
    }

    /**
     * Load models by id, keeping the order of the id list and skipping ids that no longer qualify.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, int>  $ids
     * @param  array<int, string>  $with
     * @return Collection<int, TModel>
     */
    private function loadInOrder(string $model, array $ids, array $with = []): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $loaded = $model::query()->with($with)->whereIn('id', $ids)->get()->keyBy('id');

        return new Collection(
            collect($ids)->filter(fn (int $id): bool => $loaded->has($id))->map(fn (int $id) => $loaded->get($id))->values()->all(),
        );
    }

    /**
     * Products related to a given product: same category first, highest rated.
     *
     * @return Collection<int, Product>
     */
    public function related(Product $product, int $limit = 4): Collection
    {
        return $this->productsWith(
            Product::query()->published()->where('category_id', $product->category_id)->whereKeyNot($product->getKey())->orderByDesc('rating_average'),
            $limit,
        );
    }

    /**
     * Remember a product view in the session, newest first.
     */
    public function rememberView(Product $product): void
    {
        $ids = array_values(array_filter(
            session()->get(self::RECENT_SESSION_KEY, []),
            fn (int $id): bool => $id !== $product->getKey(),
        ));

        array_unshift($ids, $product->getKey());

        session()->put(self::RECENT_SESSION_KEY, array_slice($ids, 0, self::RECENT_LIMIT));
    }

    /**
     * Recently viewed products, excluding the one currently being shown.
     *
     * @return Collection<int, Product>
     */
    public function recentlyViewed(?Product $exclude = null, int $limit = 4): Collection
    {
        $ids = array_values(array_filter(
            session()->get(self::RECENT_SESSION_KEY, []),
            fn (int $id): bool => $exclude === null || $id !== $exclude->getKey(),
        ));

        if ($ids === []) {
            return new Collection;
        }

        $products = $this->productsWith(Product::query()->published()->whereIn('id', $ids), count($ids))
            ->keyBy('id');

        return new Collection(
            collect($ids)
                ->filter(fn (int $id): bool => $products->has($id))
                ->take($limit)
                ->map(fn (int $id) => $products->get($id))
                ->values()
                ->all(),
        );
    }

    /**
     * @param  Builder<Product>  $query
     * @return Collection<int, Product>
     */
    private function productsWith($query, int $limit): Collection
    {
        return $query->with(['defaultVariant', 'brand'])->limit($limit)->get();
    }
}
