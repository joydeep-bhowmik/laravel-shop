<?php

use App\Enums\OrderStatus;

test('a pending order can be paid or cancelled but not shipped directly', function () {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled))->toBeTrue()
        ->and(OrderStatus::Pending->canTransitionTo(OrderStatus::Shipped))->toBeFalse();
});

test('cancelled and refunded orders are terminal', function () {
    expect(OrderStatus::Cancelled->allowedTransitions())->toBe([])
        ->and(OrderStatus::Refunded->allowedTransitions())->toBe([]);
});

test('a shipped order can be delivered or returned', function () {
    expect(OrderStatus::Shipped->canTransitionTo(OrderStatus::Delivered))->toBeTrue()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Returned))->toBeTrue()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
});
