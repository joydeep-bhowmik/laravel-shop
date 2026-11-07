<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Change a variant's stock by a signed delta and record the movement.
     *
     * The variant row is locked for the duration so concurrent orders cannot oversell.
     *
     * @throws InvalidArgumentException When the change would drive stock below zero.
     */
    public function adjust(
        ProductVariant $variant,
        int $delta,
        StockMovementReason $reason,
        ?string $note = null,
        ?User $actor = null,
        ?Order $order = null,
    ): StockMovement {
        return DB::transaction(function () use ($variant, $delta, $reason, $note, $actor, $order): StockMovement {
            $locked = ProductVariant::query()->whereKey($variant->getKey())->lockForUpdate()->firstOrFail();

            $newQuantity = $locked->stock_quantity + $delta;

            if ($newQuantity < 0) {
                throw new InvalidArgumentException(
                    "Only {$locked->stock_quantity} unit(s) of {$locked->sku} are in stock.",
                );
            }

            $locked->update(['stock_quantity' => $newQuantity]);

            $variant->stock_quantity = $newQuantity;

            return StockMovement::query()->create([
                'product_variant_id' => $locked->getKey(),
                'order_id' => $order?->getKey(),
                'user_id' => $actor?->getKey(),
                'quantity_change' => $delta,
                'quantity_after' => $newQuantity,
                'reason' => $reason,
                'note' => $note,
            ]);
        });
    }
}
