<?php

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\ProductCatalogService;

function variantRow(array $overrides = []): array
{
    return array_merge([
        'id' => null,
        'sku' => fake()->unique()->bothify('SKU-????-###'),
        'name' => null,
        'price_paise' => 49900,
        'compare_at_price_paise' => null,
        'stock_quantity' => 4,
        'low_stock_threshold' => 2,
        'weight_grams' => null,
        'is_default' => true,
        'attribute_value_ids' => [],
    ], $overrides);
}

function productData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Cotton Kurta',
        'category_id' => Category::factory()->create()->id,
        'brand_id' => null,
        'type' => ProductType::Simple->value,
        'status' => ProductStatus::Published->value,
        'gst_rate' => 5,
        'attribute_value_ids' => [],
        'variants' => [variantRow()],
    ], $overrides);
}

test('saving a new product creates it with its opening stock and publishes it', function () {
    $product = app(ProductCatalogService::class)->save(null, productData());

    $variant = $product->variants()->firstOrFail();

    expect($product->slug)->toBe('cotton-kurta')
        ->and($product->published_at)->not->toBeNull()
        ->and($variant->price_paise)->toBe(49900)
        ->and($variant->stock_quantity)->toBe(4)
        ->and($variant->is_default)->toBeTrue();
});

test('a simple product keeps only its first variant', function () {
    $product = app(ProductCatalogService::class)->save(null, productData([
        'variants' => [variantRow(['sku' => 'ONE-1']), variantRow(['sku' => 'TWO-2'])],
    ]));

    expect($product->variants()->pluck('sku')->all())->toBe(['ONE-1']);
});

test('a variable product keeps every variant and one of them is default', function () {
    $product = app(ProductCatalogService::class)->save(null, productData([
        'type' => ProductType::Variable->value,
        'variants' => [
            variantRow(['sku' => 'S-1', 'is_default' => false]),
            variantRow(['sku' => 'M-1', 'is_default' => false]),
        ],
    ]));

    expect($product->variants()->count())->toBe(2)
        ->and($product->defaultVariant->sku)->toBe('S-1');
});

test('a scheduled product without a publish date is rejected', function () {
    app(ProductCatalogService::class)->save(null, productData(['status' => ProductStatus::Scheduled->value, 'scheduled_for' => null]));
})->throws(InvalidArgumentException::class, 'needs a publish date');

test('removing a variant that has been ordered deactivates it instead of deleting it', function () {
    $service = app(ProductCatalogService::class);
    $product = $service->save(null, productData([
        'type' => ProductType::Variable->value,
        'variants' => [variantRow(['sku' => 'KEEP-1']), variantRow(['sku' => 'GONE-1', 'is_default' => false])],
    ]));

    $gone = $product->variants()->where('sku', 'GONE-1')->firstOrFail();
    OrderItem::factory()->for(Order::factory())->create(['product_variant_id' => $gone->id, 'sku' => 'GONE-1']);

    $keepId = $product->variants()->where('sku', 'KEEP-1')->value('id');

    $service->save($product, productData([
        'type' => ProductType::Variable->value,
        'variants' => [variantRow(['id' => $keepId, 'sku' => 'KEEP-1'])],
    ]));

    expect(ProductVariant::query()->whereKey($gone->id)->first()?->is_active)->toBeFalse()
        ->and($product->variants()->where('sku', 'KEEP-1')->exists())->toBeTrue();
});
