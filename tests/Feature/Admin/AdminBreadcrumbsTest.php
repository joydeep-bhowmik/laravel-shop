<?php

use App\Enums\AdminRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(AdminRole::SuperAdmin->value);
});

test('a list page shows the admin home and the current section', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('aria-current="page"', false);
});

test('a product edit page links back to products and names the product', function () {
    $product = Product::factory()->create(['name' => 'Breadcrumb Kurta']);
    ProductVariant::factory()->default()->for($product)->create();

    $this->actingAs($this->superAdmin)
        ->get(route('admin.products.edit', ['product' => $product]))
        ->assertOk()
        ->assertSee(route('admin.products.index'), false)
        ->assertSee('Breadcrumb Kurta');
});

test('an order page shows the order number as the current crumb', function () {
    $order = Order::factory()->paid()->create(['order_number' => 'ORD-CRUMB1']);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.orders.show', ['order' => $order]))
        ->assertOk()
        ->assertSee('Order ORD-CRUMB1')
        ->assertSee(route('admin.orders.index'), false);
});

test('the dashboard trail shows the current page as plain text', function () {
    $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('<span aria-current="page"', false)
        ->assertSee('Dashboard</span>', false);
});
