<?php

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\ProductStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\AdminRoleService;
use App\Services\CustomerAdminService;
use App\Services\CustomerCartService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function customerTestStaff(AdminRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function customerTestVariant(int $stock = 5, int $pricePaise = 10000): ProductVariant
{
    $product = Product::factory()->create(['status' => ProductStatus::Published, 'published_at' => null]);

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'is_active' => true,
        'price_paise' => $pricePaise,
        'stock_quantity' => $stock,
    ]);
}

test('a super admin promotes a customer to support and the customer gets admin access', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    $customer = User::factory()->create();

    app(CustomerAdminService::class)->syncRoles($superAdmin, $customer, [AdminRole::Support->value]);

    $this->actingAs($customer)->get(route('admin.orders.index'))->assertOk();
});

test('an admin without user management cannot promote anyone', function () {
    $admin = customerTestStaff(AdminRole::Admin);
    $customer = User::factory()->create();

    app(CustomerAdminService::class)->syncRoles($admin, $customer, [AdminRole::Support->value]);
})->throws(AuthorizationException::class);

test('only a super admin can grant the super admin role', function () {
    $userManager = User::factory()->create();
    $userManager->givePermissionTo(AdminPermission::UsersManage->value);
    $customer = User::factory()->create();

    app(CustomerAdminService::class)->syncRoles($userManager, $customer, [AdminRole::SuperAdmin->value]);
})->throws(InvalidArgumentException::class, 'grant the super admin');

test('nobody can change their own roles', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);

    app(CustomerAdminService::class)->syncRoles($superAdmin, $superAdmin, []);
})->throws(InvalidArgumentException::class, 'own roles');

test('a blocked customer is signed out on their next request', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    $customer = User::factory()->create();

    app(CustomerAdminService::class)->setBlocked($superAdmin, $customer, true);

    expect($customer->fresh()->is_blocked)->toBeTrue();

    $this->actingAs($customer)->get(route('dashboard'))->assertRedirect(route('login'));
});

test('unblocking restores access', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    $customer = User::factory()->create(['is_blocked' => true]);

    app(CustomerAdminService::class)->setBlocked($superAdmin, $customer, false);

    expect($customer->fresh()->is_blocked)->toBeFalse();
});

test('an admin cannot block a staff account', function () {
    $admin = customerTestStaff(AdminRole::Admin);
    $support = customerTestStaff(AdminRole::Support);

    app(CustomerAdminService::class)->setBlocked($admin, $support, true);
})->throws(InvalidArgumentException::class, 'super admin');

test('an admin can block a customer but not themselves', function () {
    $admin = customerTestStaff(AdminRole::Admin);

    app(CustomerAdminService::class)->setBlocked($admin, $admin, true);
})->throws(InvalidArgumentException::class, 'own account');

test('support cannot block accounts at all', function () {
    $support = customerTestStaff(AdminRole::Support);

    app(CustomerAdminService::class)->setBlocked($support, User::factory()->create(), true);
})->throws(AuthorizationException::class);

test('the users page lists customers and shows the role menu only with user management', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    User::factory()->create(['name' => 'Asha Customer']);

    $this->actingAs($superAdmin);

    Livewire::test('admin.users.index')
        ->assertSee('Asha Customer')
        ->assertSee('Roles');
});

test('creating a custom role grants exactly the chosen permissions', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    $this->actingAs($superAdmin);

    $role = app(AdminRoleService::class)->create('Content editor', [
        AdminPermission::ProductsView->value,
        AdminPermission::ProductsUpdate->value,
    ]);

    expect($role->name)->toBe('content-editor')
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe([AdminPermission::ProductsUpdate->value, AdminPermission::ProductsView->value]);
});

test('a custom role with a permission can open the admin panel', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    $this->actingAs($superAdmin);

    $role = app(AdminRoleService::class)->create('Reviewer', [AdminPermission::ReviewsModerate->value]);

    $reviewer = User::factory()->create();
    $reviewer->assignRole($role);

    $this->actingAs($reviewer)->get(route('admin.reviews.index'))->assertOk();
    $this->actingAs($reviewer)->get(route('admin.settings.index'))->assertForbidden();
});

test('built-in role names are reserved', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    $this->actingAs($superAdmin);

    app(AdminRoleService::class)->create('Admin', []);
})->throws(InvalidArgumentException::class, 'reserved');

test('built-in roles cannot be edited or deleted', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    $this->actingAs($superAdmin);

    $support = Role::query()->where('name', AdminRole::Support->value)->firstOrFail();

    app(AdminRoleService::class)->delete($support);
})->throws(InvalidArgumentException::class, 'cannot be deleted');

test('a custom role still assigned to someone cannot be deleted', function () {
    $superAdmin = customerTestStaff(AdminRole::SuperAdmin);
    $this->actingAs($superAdmin);

    $service = app(AdminRoleService::class);
    $role = $service->create('Temporary', [AdminPermission::ReviewsModerate->value]);

    $holder = User::factory()->create();
    $holder->assignRole($role);

    $service->delete($role);
})->throws(InvalidArgumentException::class, 'Remove this role');

test('support can add a product to a customer cart and it respects stock', function () {
    $support = customerTestStaff(AdminRole::Support);
    $this->actingAs($support);

    $customer = User::factory()->create();
    $variant = customerTestVariant(stock: 2);

    $cart = app(CustomerCartService::class);
    $cart->addVariant($customer, $variant, 2);

    expect(CartItem::query()->sum('quantity'))->toBe(2);

    $cart->addVariant($customer, $variant, 1);
})->throws(InvalidArgumentException::class, 'Only 2 in stock');

test('a manager without customer support cannot change a cart', function () {
    $manager = customerTestStaff(AdminRole::Manager);
    $this->actingAs($manager);

    app(CustomerCartService::class)->addVariant(User::factory()->create(), customerTestVariant(), 1);
})->throws(AuthorizationException::class);

test('support applies a coupon to a customer cart and the totals reflect it', function () {
    $support = customerTestStaff(AdminRole::Support);
    $this->actingAs($support);

    $customer = User::factory()->create();
    $cart = app(CustomerCartService::class);
    $cart->addVariant($customer, customerTestVariant(stock: 5, pricePaise: 100000), 1);

    Coupon::factory()->create(['code' => 'TENOFF', 'type' => 'percentage', 'value' => 10]);

    $cart->applyCoupon($customer, 'tenoff');

    expect($cart->summary($customer))->toMatchArray([
        'subtotal' => 100000,
        'discount' => 10000,
        'total' => 90000,
        'coupon' => 'TENOFF',
    ]);
});

test('the customer page shows the wishlist and the cart', function () {
    $support = customerTestStaff(AdminRole::Support);
    $this->actingAs($support);

    $customer = User::factory()->create();
    $variant = customerTestVariant();
    $wished = Product::factory()->create(['name' => 'Wished Kurta', 'status' => ProductStatus::Published]);
    Wishlist::factory()->create(['user_id' => $customer->id, 'product_id' => $wished->id]);

    app(CustomerCartService::class)->addVariant($customer, $variant, 1);

    Livewire::test('admin.customers.show', ['customer' => $customer->id])
        ->assertSee('Wished Kurta')
        ->assertSee($variant->product->name);
});

test('the admin panel link in the footer is shown only to admin staff', function () {
    $admin = customerTestStaff(AdminRole::Support);
    $customer = User::factory()->create();

    $this->actingAs($admin)->get(route('home'))->assertSee('Admin panel');
    $this->actingAs($customer)->get(route('home'))->assertDontSee('Admin panel');
    $this->get(route('home'))->assertDontSee('Admin panel');
});

test('support finds a product by option and sees every variant with its options', function () {
    $support = customerTestStaff(AdminRole::Support);
    $this->actingAs($support);

    $customer = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Cotton Kurta', 'status' => ProductStatus::Published, 'published_at' => null]);

    $size = Attribute::query()->create(['name' => 'Size', 'slug' => 'size', 'is_variant' => true, 'position' => 1]);
    $colour = Attribute::query()->create(['name' => 'Colour', 'slug' => 'colour', 'is_variant' => true, 'position' => 2]);
    $sizeM = AttributeValue::query()->create(['attribute_id' => $size->id, 'value' => 'M', 'slug' => 'm', 'position' => 1]);
    $colourRed = AttributeValue::query()->create(['attribute_id' => $colour->id, 'value' => 'Red', 'slug' => 'red', 'position' => 1]);

    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true, 'stock_quantity' => 4]);
    $variant->attributeValues()->attach([$sizeM->id, $colourRed->id]);

    Livewire::test('admin.customers.show', ['customer' => $customer->id])
        ->set('productSearch', 'Red')
        ->assertSee('Cotton Kurta')
        ->assertSee('Size: M · Colour: Red')
        ->call('addVariant', $variant->id)
        ->assertHasNoErrors();

    expect(CartItem::query()->where('product_variant_id', $variant->id)->value('quantity'))->toBe(1);
});
