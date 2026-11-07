<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderStatusService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * Move an order to a new status, stamp the matching timestamp and restock when the order is cancelled or returned.
     *
     * @throws InvalidArgumentException When the transition is not allowed.
     */
    public function transition(Order $order, OrderStatus $next, ?User $actor = null, ?string $note = null): Order
    {
        $current = $order->status;

        if (! $current->canTransitionTo($next)) {
            throw new InvalidArgumentException("An order cannot move from {$current->label()} to {$next->label()}.");
        }

        return DB::transaction(function () use ($order, $current, $next, $actor, $note): Order {
            if (in_array($next, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
                $reason = $next === OrderStatus::Cancelled
                    ? StockMovementReason::OrderCancelled
                    : StockMovementReason::OrderReturned;

                foreach ($order->items()->whereNotNull('product_variant_id')->with('productVariant')->get() as $item) {
                    $this->inventory->adjust(
                        $item->productVariant,
                        $item->quantity,
                        $reason,
                        "Order {$order->order_number}",
                        $actor,
                        $order,
                    );
                }
            }

            $attributes = ['status' => $next];

            $timestampColumn = $this->timestampColumnFor($next);

            if ($timestampColumn !== null) {
                $attributes[$timestampColumn] = now();
            }

            if ($next === OrderStatus::Cancelled) {
                $attributes['cancellation_reason'] = $note;
            }

            $order->forceFill($attributes)->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->getKey(),
                'user_id' => $actor?->getKey(),
                'from_status' => $current,
                'to_status' => $next,
                'note' => $note,
            ]);

            return $order->refresh();
        });
    }

    /**
     * Name of the timestamp column that records when an order reached a status.
     */
    private function timestampColumnFor(OrderStatus $status): ?string
    {
        return match ($status) {
            OrderStatus::Paid => 'paid_at',
            OrderStatus::Shipped => 'shipped_at',
            OrderStatus::Delivered => 'delivered_at',
            OrderStatus::Cancelled => 'cancelled_at',
            OrderStatus::Returned => 'returned_at',
            OrderStatus::Refunded => 'refunded_at',
            OrderStatus::Pending, OrderStatus::Processing => null,
        };
    }
}
