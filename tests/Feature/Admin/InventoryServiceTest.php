<?php

use App\Enums\StockMovementReason;
use App\Models\ProductVariant;
use App\Services\InventoryService;

test('adjusting stock updates the quantity and records a movement', function () {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 10]);

    $movement = app(InventoryService::class)->adjust($variant, 5, StockMovementReason::Restock, 'Delivery');

    expect($variant->fresh()->stock_quantity)->toBe(15)
        ->and($movement->quantity_change)->toBe(5)
        ->and($movement->quantity_after)->toBe(15)
        ->and($movement->reason)->toBe(StockMovementReason::Restock);
});

test('stock can never go below zero', function () {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 2]);

    expect(fn () => app(InventoryService::class)->adjust($variant, -3, StockMovementReason::Adjustment))
        ->toThrow(InvalidArgumentException::class, 'Only 2 unit(s)');

    expect($variant->fresh()->stock_quantity)->toBe(2);
});

test('a variant is low on stock at or below its threshold', function () {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 5, 'low_stock_threshold' => 5]);

    expect($variant->isLowStock())->toBeTrue()
        ->and($variant->isInStock())->toBeTrue();
});
