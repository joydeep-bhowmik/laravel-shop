<?php

namespace App\Services;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * Creates, renames, re-permissions and deletes custom admin roles.
 *
 * The four built-in roles are fixed in code (see AdminRole) so a re-run of the seeder cannot silently undo edits.
 */
class AdminRoleService
{
    public function isBuiltIn(Role $role): bool
    {
        return in_array($role->name, self::builtInNames(), true);
    }

    /**
     * @param  array<int, string>  $permissionNames
     *
     * @throws InvalidArgumentException
     */
    public function create(string $name, array $permissionNames): Role
    {
        Gate::authorize('roles.manage');

        $role = Role::query()->create([
            'name' => $this->assertAvailableName($name, null),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($this->knownPermissions($permissionNames));

        return $role;
    }

    /**
     * @param  array<int, string>  $permissionNames
     *
     * @throws InvalidArgumentException
     */
    public function update(Role $role, string $name, array $permissionNames): void
    {
        Gate::authorize('roles.manage');

        if ($this->isBuiltIn($role)) {
            throw new InvalidArgumentException(__('Built-in roles cannot be changed.'));
        }

        $role->update(['name' => $this->assertAvailableName($name, $role)]);
        $role->syncPermissions($this->knownPermissions($permissionNames));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function delete(Role $role): void
    {
        Gate::authorize('roles.manage');

        if ($this->isBuiltIn($role)) {
            throw new InvalidArgumentException(__('Built-in roles cannot be deleted.'));
        }

        if ($role->users()->exists()) {
            throw new InvalidArgumentException(__('Remove this role from every user before deleting it.'));
        }

        $role->delete();
    }

    /**
     * @return array<int, string>
     */
    public static function builtInNames(): array
    {
        return array_map(fn (AdminRole $role): string => $role->value, AdminRole::cases());
    }

    /**
     * @param  array<int, string>  $permissionNames
     * @return array<int, string>
     */
    private function knownPermissions(array $permissionNames): array
    {
        return array_values(array_intersect($permissionNames, AdminPermission::values()));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertAvailableName(string $name, ?Role $role): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            throw new InvalidArgumentException(__('Enter a role name with letters or numbers.'));
        }

        if (in_array($slug, self::builtInNames(), true)) {
            throw new InvalidArgumentException(__('That name is reserved for a built-in role.'));
        }

        $taken = Role::query()
            ->where('name', $slug)
            ->when($role !== null, fn ($query) => $query->whereKeyNot($role->getKey()))
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException(__('A role with that name already exists.'));
        }

        return $slug;
    }
}
