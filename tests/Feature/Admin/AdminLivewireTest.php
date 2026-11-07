<?php

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function staff(AdminRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

test('a manager can create a product with a variant through the form', function () {
    $manager = staff(AdminRole::Manager);
    $category = Category::factory()->create();

    $this->actingAs($manager);

    Livewire::test('pages::admin.products.form')
        ->set('name', 'Linen Shirt')
        ->set('category_id', $category->id)
        ->set('status', 'published')
        ->set('variants.0.sku', 'LINEN-SHIRT-M')
        ->set('variants.0.price', '1299.00')
        ->set('variants.0.stock', '12')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('name', 'Linen Shirt')->firstOrFail();

    expect($product->variants()->where('sku', 'LINEN-SHIRT-M')->value('price_paise'))->toBe(129900)
        ->and($product->variants()->first()->stock_quantity)->toBe(12);
});

test('the product form rejects a duplicate sku', function () {
    $manager = staff(AdminRole::Manager);
    $category = Category::factory()->create();
    $existing = Product::factory()->for($category, 'category')->create();
    $existing->variants()->create([
        'sku' => 'TAKEN-SKU',
        'price_paise' => 1000,
        'stock_quantity' => 1,
        'low_stock_threshold' => 1,
        'is_default' => true,
        'is_active' => true,
        'position' => 0,
    ]);

    $this->actingAs($manager);

    Livewire::test('pages::admin.products.form')
        ->set('name', 'Another')
        ->set('category_id', $category->id)
        ->set('variants.0.sku', 'TAKEN-SKU')
        ->set('variants.0.price', '10.00')
        ->set('variants.0.stock', '1')
        ->call('save')
        ->assertHasErrors(['variants.0.sku']);
});

test('a support user can move an order through the workflow from the order page', function () {
    $support = staff(AdminRole::Support);
    $order = Order::factory()->paid()->create(['status' => OrderStatus::Paid]);

    $this->actingAs($support);

    Livewire::test('pages::admin.orders.show', ['order' => $order->id])
        ->call('moveTo', OrderStatus::Processing->value)
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

test('support cannot cancel an order because that needs orders.cancel', function () {
    $support = staff(AdminRole::Support);
    $order = Order::factory()->paid()->create(['status' => OrderStatus::Paid]);

    $this->actingAs($support);

    Livewire::test('pages::admin.orders.show', ['order' => $order->id])
        ->call('moveTo', OrderStatus::Cancelled->value);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('saving settings stores the store name and keeps an existing secret when left blank', function () {
    $admin = staff(AdminRole::SuperAdmin);
    app(SettingsService::class)->set('razorpay.key_secret', 'kept-secret');

    $this->actingAs($admin);

    Livewire::test('pages::admin.settings.index')
        ->set('storeName', 'Bazaar Bhai')
        ->set('storeState', 'Maharashtra')
        ->set('shippingFlatRupees', '49.00')
        ->set('shippingFreeThresholdRupees', '499.00')
        ->set('shippingStandardDays', 4)
        ->set('codMaxOrderRupees', '5000.00')
        ->set('razorpayMode', 'test')
        ->set('emailFromName', 'Bazaar Bhai')
        ->set('emailFromAddress', 'hello@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsService::class);

    expect($settings->get('store.name'))->toBe('Bazaar Bhai')
        ->and($settings->int('shipping.flat_rate_paise'))->toBe(4900)
        ->and($settings->get('razorpay.key_secret'))->toBe('kept-secret');
});
