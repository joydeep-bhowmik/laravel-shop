<?php

use App\Enums\AdminRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

test('top products link to the product they belong to', function () {
    $product = Product::factory()->create(['name' => 'Quick Action Kurta']);
    $variant = ProductVariant::factory()->default()->for($product)->create();
    $order = Order::factory()->paid()->create(['placed_at' => now()]);
    OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id, 'product_name' => 'Quick Action Kurta', 'quantity' => 3]);

    $top = Livewire::test('admin.dashboard')->instance()->topProducts();

    expect($top->first()->product_id)->toBe($product->id)
        ->and($top->first()->units)->toBe(3);
});

test('low stock rows open the inventory page filtered to the SKU', function () {
    $variant = ProductVariant::factory()->for(Product::factory()->create())->create(['stock_quantity' => 0, 'low_stock_threshold' => 5, 'sku' => 'QA-LOW-1']);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.inventory.index', ['q' => $variant->sku]), false);
});
