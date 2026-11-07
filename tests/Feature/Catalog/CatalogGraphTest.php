<?php

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;

test('a variable product exposes its default variant and selectable options', function () {
    $product = Product::factory()->variable()->create();
    $size = AttributeValue::factory()->for(Attribute::factory()->variant()->create(['name' => 'Size']), 'attribute')->create(['value' => 'M']);
    $default = ProductVariant::factory()->default()->for($product)->create(['stock_quantity' => 5]);
    $default->attributeValues()->attach($size);
    ProductVariant::factory()->for($product)->outOfStock()->create();

    $product->refresh();

    expect($product->variants)->toHaveCount(2)
        ->and($product->defaultVariant->is($default))->toBeTrue()
        ->and($default->attributeValues->first()->value)->toBe('M')
        ->and($default->isInStock())->toBeTrue();
});

test('only published products that are not scheduled in the future are visible', function () {
    $live = Product::factory()->create();
    Product::factory()->draft()->create();
    Product::factory()->scheduled()->create();

    expect(Product::query()->published()->pluck('id')->all())->toBe([$live->id]);
});

test('categories nest and products belong to their category', function () {
    $parent = Category::factory()->create(['name' => 'Clothing']);
    $child = Category::factory()->childOf($parent)->create(['name' => 'Kurtas']);
    $product = Product::factory()->for($child, 'category')->create();

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children->first()->is($child))->toBeTrue()
        ->and($product->category->is($child))->toBeTrue();
});

test('an order keeps item snapshots and a status history timeline', function () {
    $order = Order::factory()->paid()->create();
    OrderItem::factory()->for($order)->create(['sku' => 'SKU-KEEP-0001', 'quantity' => 2, 'unit_price_paise' => 49900, 'line_total_paise' => 99800]);
    OrderStatusHistory::factory()->for($order)->create([
        'from_status' => OrderStatus::Pending,
        'to_status' => OrderStatus::Paid,
    ]);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->items->first()->sku)->toBe('SKU-KEEP-0001')
        ->and($order->statusHistories->first()->to_status)->toBe(OrderStatus::Paid);
});

test('coupon validity respects its start and expiry window', function () {
    $coupon = Coupon::factory()->create([
        'starts_at' => Carbon::parse('2026-01-01 00:00:00'),
        'expires_at' => Carbon::parse('2026-01-31 23:59:59'),
    ]);

    expect($coupon->isActiveAt(Carbon::parse('2025-12-31 12:00:00')))->toBeFalse()
        ->and($coupon->isActiveAt(Carbon::parse('2026-01-15 12:00:00')))->toBeTrue()
        ->and($coupon->isActiveAt(Carbon::parse('2026-02-01 12:00:00')))->toBeFalse();
});

test('a deal countdown only runs while the end time is in the future', function () {
    $deal = Product::factory()->onDeal()->create();
    $expired = Product::factory()->create(['deal_ends_at' => now()->subHour()]);

    expect($deal->isOnDeal())->toBeTrue()
        ->and($expired->isOnDeal())->toBeFalse()
        ->and($deal->status)->toBe(ProductStatus::Published);
});
