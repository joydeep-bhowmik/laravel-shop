<?php

use App\Models\Pincode;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\ReviewService;

test('a new review does not change the rating until it is approved', function () {
    $product = Product::factory()->create(['rating_average' => 0, 'rating_count' => 0]);
    $user = User::factory()->create();

    $review = app(ReviewService::class)->submit($user, $product, 5, 'Great', 'Works well');

    expect($review->is_approved)->toBeFalse()
        ->and($product->fresh()->rating_count)->toBe(0);

    app(ReviewService::class)->approve($review);

    expect($product->fresh()->rating_count)->toBe(1)
        ->and((float) $product->fresh()->rating_average)->toBe(5.0);
});

test('the rating average is recalculated from approved reviews when one is removed', function () {
    $product = Product::factory()->create();
    $service = app(ReviewService::class);

    ProductReview::factory()->for($product)->for(User::factory())->create(['rating' => 4]);
    $two = ProductReview::factory()->for($product)->for(User::factory())->create(['rating' => 2]);
    $service->refreshRating($product);

    expect((float) $product->fresh()->rating_average)->toBe(3.0);

    $service->delete($two);

    expect((float) $product->fresh()->rating_average)->toBe(4.0)
        ->and($product->fresh()->rating_count)->toBe(1);
});

test('a user can review a product only once', function () {
    $product = Product::factory()->create();
    $user = User::factory()->create();
    $service = app(ReviewService::class);

    $service->submit($user, $product, 4, null, null);

    expect(fn () => $service->submit($user, $product, 5, null, null))
        ->toThrow(InvalidArgumentException::class, 'already reviewed');
});

test('delivery check reports serviceability, cash on delivery and the estimated date', function () {
    Pincode::factory()->create(['code' => '400050', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'delivery_days' => 3, 'cod_available' => true]);
    Pincode::factory()->create(['code' => '600001', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'cod_available' => false]);
    Pincode::factory()->unserviceable()->create(['code' => '785001', 'city' => 'Jorhat', 'state' => 'Assam']);

    $service = app(DeliveryService::class);

    $mumbai = $service->check('400050');
    expect($mumbai['serviceable'])->toBeTrue()
        ->and($mumbai['cod'])->toBeTrue()
        ->and($mumbai['estimated_on'])->toBe(now()->addDays(3)->format('D, d M Y'));

    expect($service->check('600001'))->toMatchArray(['serviceable' => true, 'cod' => false]);
    expect($service->check('785001'))->toMatchArray(['serviceable' => false, 'cod' => false]);
});

test('delivery check rejects malformed and unknown pincodes', function () {
    $service = app(DeliveryService::class);

    expect($service->check('12345'))->toBeNull()
        ->and($service->check('012345'))->toBeNull()
        ->and($service->check('999999'))->toBeNull();
});
