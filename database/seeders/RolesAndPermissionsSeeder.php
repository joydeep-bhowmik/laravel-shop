<?php

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Create every admin permission and role, then sync each role's permissions.
     *
     * Idempotent: safe to run again after new permissions are added to the enums.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (AdminRole::cases() as $adminRole) {
            $role = Role::findOrCreate($adminRole->value, 'web');

            $role->syncPermissions(
                array_map(fn (AdminPermission $permission): string => $permission->value, $adminRole->permissions()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
