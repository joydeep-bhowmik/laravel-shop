<?php

use App\Enums\AdminRole;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Services\CouponService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->customer = User::factory()->create();
});

test('a percentage coupon takes its share of the subtotal, capped at the maximum', function () {
    $coupon = Coupon::factory()->create(['type' => CouponType::Percentage, 'value' => 10, 'max_discount_paise' => 20000]);
    $service = app(CouponService::class);

    expect($service->discountFor($coupon, 100000))->toBe(10000)
        ->and($service->discountFor($coupon, 500000))->toBe(20000);
});

test('a fixed coupon takes a set amount but never more than the subtotal', function () {
    $coupon = Coupon::factory()->fixed(10000)->create();
    $service = app(CouponService::class);

    expect($service->discountFor($coupon, 50000))->toBe(10000)
        ->and($service->discountFor($coupon, 4000))->toBe(4000);
});

test('an unknown code is refused with a clear message', function () {
    app(CouponService::class)->validate('NOPE', $this->customer, 100000);
})->throws(InvalidArgumentException::class, 'does not exist');

test('a coupon outside its dates is refused', function () {
    Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);

    app(CouponService::class)->validate('OLD', $this->customer, 100000);
})->throws(InvalidArgumentException::class, 'not active');

test('a coupon needs the minimum order value', function () {
    Coupon::factory()->create(['code' => 'BIG', 'min_order_paise' => 99900]);

    app(CouponService::class)->validate('big', $this->customer, 50000);
})->throws(InvalidArgumentException::class, 'at least');

test('a coupon stops at its total usage limit', function () {
    Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 1, 'usage_count' => 1]);

    app(CouponService::class)->validate('ONCE', $this->customer, 100000);
})->throws(InvalidArgumentException::class, 'used up');

test('a customer cannot use a coupon more often than the per-customer limit', function () {
    $coupon = Coupon::factory()->create(['code' => 'MINE', 'per_user_limit' => 1]);
    $service = app(CouponService::class);

    $service->recordUsage($coupon, $this->customer, Order::factory()->create(['user_id' => $this->customer->id]));

    $service->validate('MINE', $this->customer, 100000);
})->throws(InvalidArgumentException::class, 'already used');

test('recording usage increments the count and writes a usage row', function () {
    $coupon = Coupon::factory()->create(['code' => 'COUNTED']);
    $order = Order::factory()->create(['user_id' => $this->customer->id]);

    app(CouponService::class)->recordUsage($coupon, $this->customer, $order);

    expect($coupon->fresh()->usage_count)->toBe(1)
        ->and($coupon->usages()->where('order_id', $order->id)->exists())->toBeTrue();
});

test('a valid code is accepted for a signed-in customer', function () {
    Coupon::factory()->create(['code' => 'WELCOME10', 'type' => CouponType::Percentage, 'value' => 10]);

    $coupon = app(CouponService::class)->validate('welcome10', $this->customer, 100000, Carbon::now());

    expect($coupon->code)->toBe('WELCOME10');
});

test('the apply widget stores the coupon in the session for checkout', function () {
    Coupon::factory()->create(['code' => 'SAVE5', 'type' => CouponType::Percentage, 'value' => 5]);

    $this->actingAs($this->customer);

    Livewire::test('store.coupon-apply', ['subtotalPaise' => 100000])
        ->set('code', 'save5')
        ->call('apply')
        ->assertHasNoErrors();

    expect(session('checkout.coupon'))->toBe('SAVE5');
});

test('the apply widget asks guests to log in', function () {
    Coupon::factory()->create(['code' => 'GUESTS']);

    Livewire::test('store.coupon-apply', ['subtotalPaise' => 100000])
        ->set('code', 'GUESTS')
        ->call('apply')
        ->assertHasErrors(['code']);
});

test('the admin coupons page needs the coupons permission', function () {
    $support = User::factory()->create();
    $support->assignRole(AdminRole::Support->value);

    $manager = User::factory()->create();
    $manager->assignRole(AdminRole::Manager->value);

    $this->actingAs($support)->get(route('admin.coupons.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('admin.coupons.index'))->assertOk();
});

test('an admin can create a percentage coupon from the form', function () {
    $manager = User::factory()->create();
    $manager->assignRole(AdminRole::Manager->value);
    $this->actingAs($manager);

    Livewire::test('admin.coupons.index')
        ->call('create')
        ->set('code', 'summer15')
        ->set('type', 'percentage')
        ->set('value', '15')
        ->set('maxDiscount', '300')
        ->call('save')
        ->assertHasNoErrors();

    $coupon = Coupon::query()->where('code', 'SUMMER15')->firstOrFail();

    expect($coupon->value)->toBe(15)
        ->and($coupon->max_discount_paise)->toBe(30000);
});
