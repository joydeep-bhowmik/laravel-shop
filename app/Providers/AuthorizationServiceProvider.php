<?php

namespace App\Providers;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Let super admins pass every ability check.
     *
     * Blocked accounts are rejected at login and by EnsureUserIsNotBlocked, not here.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(AdminRole::SuperAdmin->value) ? true : null);

        Gate::define('admin.access', fn (User $user): bool => $user->hasAnyRole(array_map(
            fn (AdminRole $role): string => $role->value,
            AdminRole::cases(),
        )));
    }
}
