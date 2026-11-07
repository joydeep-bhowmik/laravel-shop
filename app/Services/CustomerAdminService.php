<?php

namespace App\Services;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Promotes, demotes, blocks and unblocks accounts on behalf of an admin.
 *
 * Admins with users.manage or customers.block can act on customers. Only a super admin can act on admin accounts.
 */
class CustomerAdminService
{
    /**
     * Replace the roles a user holds.
     *
     * @param  array<int, string>  $roleNames
     *
     * @throws InvalidArgumentException With a message the admin can read when the change is refused.
     */
    public function syncRoles(User $actor, User $target, array $roleNames): void
    {
        Gate::forUser($actor)->authorize('users.manage');

        if ($actor->is($target)) {
            throw new InvalidArgumentException(__('You cannot change your own roles.'));
        }

        $this->guardAdminAccount($actor, $target);

        if (in_array(AdminRole::SuperAdmin->value, $roleNames, true) && ! $this->isSuperAdmin($actor)) {
            throw new InvalidArgumentException(__('Only a super admin can grant the super admin role.'));
        }

        $target->syncRoles($roleNames);
    }

    /**
     * Block or unblock an account. Blocked users are signed out on their next request.
     *
     * @throws InvalidArgumentException
     */
    public function setBlocked(User $actor, User $target, bool $blocked): void
    {
        Gate::forUser($actor)->authorize('customers.block');

        if ($actor->is($target)) {
            throw new InvalidArgumentException(__('You cannot block your own account.'));
        }

        $this->guardAdminAccount($actor, $target);

        $target->forceFill(['is_blocked' => $blocked])->save();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function guardAdminAccount(User $actor, User $target): void
    {
        if ($this->isSuperAdmin($actor)) {
            return;
        }

        if ($target->hasAnyPermission(AdminPermission::values())) {
            throw new InvalidArgumentException(__('Only a super admin can change this admin account.'));
        }
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(AdminRole::SuperAdmin->value);
    }
}
