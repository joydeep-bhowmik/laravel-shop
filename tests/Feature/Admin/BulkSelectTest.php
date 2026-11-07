<?php

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(AdminRole::SuperAdmin->value);
    $this->actingAs($admin);
});

test('bulk status moves valid orders and skips the ones that cannot move', function () {
    $paid = Order::factory()->paid()->create(['status' => OrderStatus::Paid]);
    $pending = Order::factory()->create(['status' => OrderStatus::Pending]);

    Livewire::test('admin.orders.index')
        ->set('selected', [(string) $paid->id, (string) $pending->id])
        ->set('bulkStatus', OrderStatus::Processing->value)
        ->call('applyBulkStatus');

    expect($paid->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($pending->fresh()->status)->toBe(OrderStatus::Pending);
});

test('select all on the orders page selects every order on that page', function () {
    Order::factory()->count(3)->create();

    $component = Livewire::test('admin.orders.index')->call('toggleAll');

    expect($component->get('selected'))->toHaveCount(3);

    $component->call('toggleAll');

    expect($component->get('selected'))->toBe([]);
});

test('bulk approve publishes the selected reviews', function () {
    $product = Product::factory()->create();
    $reviews = ProductReview::factory()->count(2)->for($product)->pending()->create();

    Livewire::test('admin.reviews.index', ['state' => 'pending'])
        ->set('selected', $reviews->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('bulkApprove');

    expect(ProductReview::query()->where('is_approved', true)->count())->toBe(2)
        ->and($product->fresh()->rating_count)->toBe(2);
});

test('select all on the products page selects products from that page', function () {
    Product::factory()->count(2)->create()->each(fn (Product $product) => ProductVariant::factory()->default()->for($product)->create());

    $component = Livewire::test('admin.products.index')->call('toggleAll');

    expect($component->get('selected'))->toHaveCount(2);
});
