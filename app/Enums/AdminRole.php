<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Support = 'support';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Support => 'Support',
        };
    }

    /**
     * Permissions granted to this role. Super admins bypass checks via Gate::before, so they receive every permission too.
     *
     * @return array<int, AdminPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => AdminPermission::cases(),
            self::Admin => array_values(array_filter(
                AdminPermission::cases(),
                fn (AdminPermission $permission): bool => ! in_array($permission, [AdminPermission::RolesManage, AdminPermission::UsersManage], true),
            )),
            self::Manager => [
                AdminPermission::ProductsView,
                AdminPermission::ProductsCreate,
                AdminPermission::ProductsUpdate,
                AdminPermission::ProductsPublish,
                AdminPermission::CatalogManage,
                AdminPermission::InventoryManage,
                AdminPermission::OrdersView,
                AdminPermission::OrdersUpdate,
                AdminPermission::OrdersCancel,
                AdminPermission::CustomersView,
                AdminPermission::CouponsManage,
                AdminPermission::BannersManage,
                AdminPermission::ReviewsModerate,
                AdminPermission::ReportsView,
            ],
            self::Support => [
                AdminPermission::ProductsView,
                AdminPermission::OrdersView,
                AdminPermission::OrdersUpdate,
                AdminPermission::CustomersView,
                AdminPermission::CustomersSupport,
                AdminPermission::ReviewsModerate,
            ],
        };
    }
}
