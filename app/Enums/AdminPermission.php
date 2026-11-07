<?php

namespace App\Enums;

enum AdminPermission: string
{
    case ProductsView = 'products.view';
    case ProductsCreate = 'products.create';
    case ProductsUpdate = 'products.update';
    case ProductsDelete = 'products.delete';
    case ProductsPublish = 'products.publish';
    case ProductsImport = 'products.import';
    case CatalogManage = 'catalog.manage';
    case InventoryManage = 'inventory.manage';
    case OrdersView = 'orders.view';
    case OrdersUpdate = 'orders.update';
    case OrdersCancel = 'orders.cancel';
    case OrdersRefund = 'orders.refund';
    case CustomersView = 'customers.view';
    case CustomersBlock = 'customers.block';
    case CustomersSupport = 'customers.support';
    case CouponsManage = 'coupons.manage';
    case BannersManage = 'banners.manage';
    case ReviewsModerate = 'reviews.moderate';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case ActivityView = 'activity.view';
    case ReportsView = 'reports.view';

    /**
     * Every permission name, as stored in the permissions table.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ProductsView => 'View products',
            self::ProductsCreate => 'Create products',
            self::ProductsUpdate => 'Edit products',
            self::ProductsDelete => 'Delete products',
            self::ProductsPublish => 'Publish and schedule products',
            self::ProductsImport => 'Import and export products',
            self::CatalogManage => 'Manage categories, brands and attributes',
            self::InventoryManage => 'Adjust stock',
            self::OrdersView => 'View orders',
            self::OrdersUpdate => 'Update order status and notes',
            self::OrdersCancel => 'Cancel orders',
            self::OrdersRefund => 'Issue refunds',
            self::CustomersView => 'View customers',
            self::CustomersBlock => 'Block and unblock customers',
            self::CustomersSupport => 'Manage customer carts, wishlists and coupons',
            self::CouponsManage => 'Manage coupons',
            self::BannersManage => 'Manage banners',
            self::ReviewsModerate => 'Moderate reviews',
            self::SettingsManage => 'Manage store settings',
            self::UsersManage => 'Manage admin users',
            self::RolesManage => 'Manage roles and permissions',
            self::ActivityView => 'View activity log',
            self::ReportsView => 'View dashboard reports',
        };
    }
}
