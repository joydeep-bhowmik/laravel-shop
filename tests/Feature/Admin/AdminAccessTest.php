<?php

use App\Enums\AdminRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function adminWithRole(AdminRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

test('guests are sent to the login page', function () {
    $this->get(route('admin.products.index'))->assertRedirect(route('login'));
});

test('customers without an admin role are forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.products.index'))
        ->assertForbidden();
});

test('support staff can view orders but not settings', function () {
    $support = adminWithRole(AdminRole::Support);

    $this->actingAs($support)->get(route('admin.orders.index'))->assertOk();
    $this->actingAs($support)->get(route('admin.settings.index'))->assertForbidden();
});

test('managers can edit products but cannot manage settings', function () {
    $manager = adminWithRole(AdminRole::Manager);

    $this->actingAs($manager)->get(route('admin.products.create'))->assertOk();
    $this->actingAs($manager)->get(route('admin.settings.index'))->assertForbidden();
});

test('the home redirect sends users without reports access to orders', function () {
    $support = adminWithRole(AdminRole::Support);
    $manager = adminWithRole(AdminRole::Manager);

    $this->actingAs($support)->get(route('admin.home'))->assertRedirect(route('admin.orders.index'));
    $this->actingAs($manager)->get(route('admin.home'))->assertRedirect(route('admin.dashboard'));
});

test('super admins can reach every admin page', function () {
    $superAdmin = adminWithRole(AdminRole::SuperAdmin);

    foreach (['admin.dashboard', 'admin.categories.index', 'admin.brands.index', 'admin.attributes.index', 'admin.products.index', 'admin.products.create', 'admin.inventory.index', 'admin.orders.index', 'admin.settings.index', 'admin.activity.index'] as $route) {
        $this->actingAs($superAdmin)->get(route($route))->assertOk();
    }
});
