<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\OrderEligibilityService;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('cash on delivery is refused when a product has it switched off', function () {
    $product = Product::factory()->create(['name' => 'Silk Saree', 'status' => ProductStatus::Published, 'cod_eligible' => false]);

    app(OrderEligibilityService::class)->ensureCodAllowed([$product]);
})->throws(InvalidArgumentException::class, 'Silk Saree');

test('cash on delivery is allowed when every product is eligible', function () {
    $products = Product::factory()->count(2)->create(['status' => ProductStatus::Published, 'cod_eligible' => true]);

    app(OrderEligibilityService::class)->ensureCodAllowed($products);

    expect($products->every(fn (Product $product) => $product->cod_eligible))->toBeTrue();
});

test('the setting defaults every product to cash on delivery eligible', function () {
    expect(app(SettingsService::class)->bool('cod.products_eligible_by_default'))->toBeTrue();
});

test('the setting can switch the default off', function () {
    app(SettingsService::class)->set('cod.products_eligible_by_default', '0');

    expect(app(SettingsService::class)->bool('cod.products_eligible_by_default'))->toBeFalse();
});
