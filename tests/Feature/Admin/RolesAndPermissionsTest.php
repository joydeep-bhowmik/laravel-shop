<?php

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('every admin role is seeded with its permissions', function () {
    $manager = Role::findByName(AdminRole::Manager->value, 'web');

    expect($manager->hasPermissionTo(AdminPermission::ProductsCreate->value))->toBeTrue()
        ->and($manager->hasPermissionTo(AdminPermission::OrdersRefund->value))->toBeFalse();
});

test('super admin passes any ability through the gate', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(AdminRole::SuperAdmin->value);

    expect(Gate::forUser($superAdmin)->allows(AdminPermission::RolesManage->value))->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('anything.undefined'))->toBeTrue();
});

test('support staff cannot issue refunds', function () {
    $support = User::factory()->create();
    $support->assignRole(AdminRole::Support->value);

    expect(Gate::forUser($support)->allows(AdminPermission::OrdersView->value))->toBeTrue()
        ->and(Gate::forUser($support)->allows(AdminPermission::OrdersRefund->value))->toBeFalse();
});

test('the seeded super admin can sign in with the default account', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    expect($admin->hasRole(AdminRole::SuperAdmin->value))->toBeTrue();
});
