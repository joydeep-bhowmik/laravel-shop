<?php

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\OrderStatusService;

test('moving an order to paid stamps paid_at and writes history', function () {
    $order = Order::factory()->create();

    $updated = app(OrderStatusService::class)->transition($order, OrderStatus::Paid, null, 'Cash received');

    expect($updated->status)->toBe(OrderStatus::Paid)
        ->and($updated->paid_at)->not->toBeNull()
        ->and($updated->statusHistories()->count())->toBe(1)
        ->and($updated->statusHistories->first()->note)->toBe('Cash received');
});

test('cancelling an order returns its stock', function () {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 3]);
    $order = Order::factory()->paid()->create();
    OrderItem::factory()->for($order)->create([
        'product_variant_id' => $variant->id,
        'sku' => $variant->sku,
        'quantity' => 2,
    ]);

    app(OrderStatusService::class)->transition($order, OrderStatus::Cancelled, null, 'Customer request');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($order->cancellation_reason)->toBe('Customer request')
        ->and($variant->fresh()->stock_quantity)->toBe(5)
        ->and(StockMovement::query()->where('reason', StockMovementReason::OrderCancelled)->value('quantity_change'))->toBe(2);
});

test('an order cannot skip straight from pending to shipped', function () {
    $order = Order::factory()->create();

    expect(fn () => app(OrderStatusService::class)->transition($order, OrderStatus::Shipped))
        ->toThrow(InvalidArgumentException::class, 'cannot move from Pending to Shipped');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});
