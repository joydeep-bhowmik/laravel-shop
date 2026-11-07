<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementReason;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductCatalogService
{
    public function __construct(private InventoryService $inventory, private RichTextSanitizer $richText) {}

    /**
     * Create or update a product together with its variants.
     *
     * Money values must already be in paise. Variant stock is only set on creation; later changes go through InventoryService.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException When a simple product has no variant, or a status needs a date it does not have.
     */
    public function save(?Product $product, array $data): Product
    {
        $type = ProductType::from($data['type']);
        $status = ProductStatus::from($data['status']);
        $variants = $this->normalizeVariants($type, $data['variants']);

        if ($status === ProductStatus::Scheduled && empty($data['scheduled_for'])) {
            throw new InvalidArgumentException('A scheduled product needs a publish date.');
        }

        return DB::transaction(function () use ($product, $data, $type, $status, $variants): Product {
            $product ??= new Product;

            $product->fill([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'type' => $type,
                'status' => $status,
                'short_description' => $data['short_description'] ?? null,
                'description' => $this->richText->clean($data['description'] ?? null),
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'hsn_code' => $data['hsn_code'] ?? null,
                'gst_rate' => $data['gst_rate'],
                'scheduled_for' => $status === ProductStatus::Scheduled ? Carbon::parse($data['scheduled_for']) : null,
                'deal_ends_at' => ! empty($data['deal_ends_at']) ? Carbon::parse($data['deal_ends_at']) : null,
            ]);

            if ($status === ProductStatus::Published && $product->published_at === null) {
                $product->published_at = now();
            }

            if ($status !== ProductStatus::Published) {
                $product->published_at = null;
            }

            $product->save();
            $product->attributeValues()->sync($data['attribute_value_ids'] ?? []);

            $this->syncVariants($product, $variants);

            return $product->refresh();
        });
    }

    /**
     * Make sure a simple product has exactly one variant and every product has one default variant.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<int, array<string, mixed>>
     */
    private function normalizeVariants(ProductType $type, array $variants): array
    {
        if ($variants === []) {
            throw new InvalidArgumentException('A product needs at least one variant.');
        }

        if ($type === ProductType::Simple) {
            $variants = [$variants[0]];
        }

        $hasDefault = collect($variants)->contains(fn (array $variant): bool => $variant['is_default']);

        foreach ($variants as $index => $variant) {
            $variants[$index]['is_default'] = $hasDefault ? $variant['is_default'] : $index === 0;
        }

        return $variants;
    }

    /**
     * Create, update and retire variants so the database matches the submitted list.
     *
     * Variants that already appear on orders are deactivated instead of deleted, which keeps order history intact.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];

        foreach ($variants as $position => $data) {
            $attributes = [
                'sku' => $data['sku'],
                'name' => $data['name'] ?? null,
                'price_paise' => $data['price_paise'],
                'compare_at_price_paise' => $data['compare_at_price_paise'] ?? null,
                'low_stock_threshold' => $data['low_stock_threshold'],
                'weight_grams' => $data['weight_grams'] ?? null,
                'is_default' => $data['is_default'],
                'is_active' => true,
                'position' => $position,
            ];

            if (! empty($data['id'])) {
                $variant = $product->variants()->whereKey($data['id'])->firstOrFail();
                $variant->update($attributes);
            } else {
                $variant = $product->variants()->create($attributes + ['stock_quantity' => 0]);

                if ($data['stock_quantity'] > 0) {
                    $this->inventory->adjust($variant, (int) $data['stock_quantity'], StockMovementReason::Restock, 'Initial stock');
                }
            }

            $variant->attributeValues()->sync($data['attribute_value_ids'] ?? []);
            $keptIds[] = $variant->getKey();
        }

        $this->retireVariantsExcept($product, $keptIds);
    }

    /**
     * @param  array<int, int>  $keptIds
     */
    private function retireVariantsExcept(Product $product, array $keptIds): void
    {
        $removed = $product->variants()->whereNotIn('id', $keptIds)->get();

        foreach ($removed as $variant) {
            if ($variant->orderItems()->exists()) {
                $variant->update(['is_active' => false, 'is_default' => false]);
            } else {
                $variant->attributeValues()->detach();
                $variant->delete();
            }
        }
    }
}
