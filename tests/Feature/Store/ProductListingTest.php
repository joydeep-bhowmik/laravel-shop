<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductListing;

function listed(array $filters): array
{
    return app(ProductListing::class)->build($filters)->pluck('products.id')->all();
}

test('listing hides drafts and scheduled products', function () {
    $live = Product::factory()->create();
    $draft = Product::factory()->draft()->create();
    $scheduled = Product::factory()->scheduled()->create();

    $ids = listed([]);

    expect($ids)->toContain($live->id)
        ->and($ids)->not->toContain($draft->id, $scheduled->id);
});

test('a category filter includes products in its nested subcategories', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();
    $inChild = Product::factory()->for($child, 'category')->create();
    $inParent = Product::factory()->for($parent, 'category')->create();
    $elsewhere = Product::factory()->create();

    $ids = listed(['category_id' => $parent->id]);

    expect($ids)->toContain($inChild->id, $inParent->id)
        ->and($ids)->not->toContain($elsewhere->id);
});

test('brand and in-stock filters narrow the results', function () {
    $brand = Brand::factory()->create();
    $inStock = Product::factory()->for($brand)->create();
    ProductVariant::factory()->default()->for($inStock)->create(['stock_quantity' => 3]);

    $outOfStock = Product::factory()->for($brand)->create();
    ProductVariant::factory()->default()->for($outOfStock)->outOfStock()->create();

    $otherBrand = Product::factory()->create();
    ProductVariant::factory()->default()->for($otherBrand)->create(['stock_quantity' => 3]);

    $ids = listed(['brand_ids' => [$brand->id], 'in_stock' => true]);

    expect($ids)->toBe([$inStock->id]);
});

test('price bounds match on any active variant', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->create(['price_paise' => 50000, 'is_default' => true]);
    ProductVariant::factory()->for($product)->create(['price_paise' => 150000]);

    $cheap = Product::factory()->create();
    ProductVariant::factory()->for($cheap)->create(['price_paise' => 900000, 'is_default' => true]);

    expect(listed(['min_price' => 40000, 'max_price' => 60000]))->toBe([$product->id])
        ->and(listed(['min_price' => 140000, 'max_price' => 160000]))->toBe([$product->id]);
});

test('attribute filters match alternatives within an attribute and require every attribute', function () {
    $size = Attribute::factory()->variant()->create(['name' => 'Size']);
    $fabric = Attribute::factory()->create(['name' => 'Fabric']);
    $medium = AttributeValue::factory()->for($size, 'attribute')->create(['value' => 'M']);
    $large = AttributeValue::factory()->for($size, 'attribute')->create(['value' => 'L']);
    $cotton = AttributeValue::factory()->for($fabric, 'attribute')->create(['value' => 'Cotton']);
    $silk = AttributeValue::factory()->for($fabric, 'attribute')->create(['value' => 'Silk']);

    $cottonMedium = Product::factory()->create();
    $cottonMedium->attributeValues()->attach($cotton);
    ProductVariant::factory()->for($cottonMedium)->create()->attributeValues()->attach($medium);

    $silkLarge = Product::factory()->create();
    $silkLarge->attributeValues()->attach($silk);
    ProductVariant::factory()->for($silkLarge)->create()->attributeValues()->attach($large);

    // Cotton OR Silk, and size M OR L: both products match.
    expect(listed(['attribute_value_ids' => [$cotton->id, $silk->id, $medium->id, $large->id]]))
        ->toContain($cottonMedium->id, $silkLarge->id);

    // Cotton AND Size M: only the first product.
    expect(listed(['attribute_value_ids' => [$cotton->id, $medium->id]]))->toBe([$cottonMedium->id]);
});

test('rating filter uses the cached average', function () {
    $rated = Product::factory()->create(['rating_average' => 4.5, 'rating_count' => 6]);
    $plain = Product::factory()->create(['rating_average' => 2.0, 'rating_count' => 1]);

    expect(listed(['min_rating' => 4]))->toBe([$rated->id])
        ->and(listed(['min_rating' => 4]))->not->toContain($plain->id);
});

test('price sorting orders by the cheapest active variant', function () {
    $mid = Product::factory()->create();
    ProductVariant::factory()->for($mid)->create(['price_paise' => 20000, 'is_default' => true]);

    $cheap = Product::factory()->create();
    ProductVariant::factory()->for($cheap)->create(['price_paise' => 10000, 'is_default' => true]);

    $dear = Product::factory()->create();
    ProductVariant::factory()->for($dear)->create(['price_paise' => 90000, 'is_default' => true]);

    expect(listed(['sort' => 'price_asc']))->toBe([$cheap->id, $mid->id, $dear->id])
        ->and(listed(['sort' => 'price_desc']))->toBe([$dear->id, $mid->id, $cheap->id]);
});

test('search matches product names through Scout', function () {
    $match = Product::factory()->create(['name' => 'Banarasi Silk Saree']);
    $miss = Product::factory()->create(['name' => 'Steel Tumbler']);

    expect(listed(['q' => 'banarasi']))->toBe([$match->id])
        ->and(listed(['q' => 'banarasi']))->not->toContain($miss->id);
});
