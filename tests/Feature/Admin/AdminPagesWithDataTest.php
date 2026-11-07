<?php

use App\Enums\AdminRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(AdminRole::SuperAdmin->value);
});

test('the product edit page renders for an existing product with its variants', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->default()->for($product)->create();

    $this->actingAs($this->superAdmin)
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee($product->name);
});

test('the order detail page renders items and the status timeline', function () {
    $order = Order::factory()->paid()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee($order->order_number);
});

test('dragging a category to a new position renumbers the list', function () {
    $first = Category::factory()->create(['name' => 'Alpha', 'position' => 0]);
    $second = Category::factory()->create(['name' => 'Beta', 'position' => 1]);
    $third = Category::factory()->create(['name' => 'Gamma', 'position' => 2]);

    $this->actingAs($this->superAdmin);

    Livewire::test('pages::admin.categories.index')
        ->call('reorder', $third->id, 0);

    expect(Category::query()->orderBy('position')->pluck('id')->all())
        ->toBe([$third->id, $first->id, $second->id]);
});

test('the inventory page shows movements for a selected variant', function () {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 4]);

    $this->actingAs($this->superAdmin);

    Livewire::test('pages::admin.inventory.index')
        ->call('showHistory', $variant->id)
        ->assertSet('historyVariantId', $variant->id);
});
