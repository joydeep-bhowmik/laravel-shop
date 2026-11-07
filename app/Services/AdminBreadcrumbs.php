<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Routing\Route;

/**
 * Builds the breadcrumb trail for an admin page from its route name and bound models.
 */
class AdminBreadcrumbs
{
    /**
     * @return array<int, array{label: string, url: ?string}> The last item is the current page and has no url.
     */
    public static function for(?Route $route): array
    {
        $name = $route?->getName();

        if ($name === null || ! str_starts_with($name, 'admin.') || $name === 'admin.home') {
            return [];
        }

        $trail = [['label' => __('Admin'), 'url' => route('admin.home'), 'icon' => 'home']];

        foreach (self::pagesFor($route) as $item) {
            $trail[] = $item;
        }

        $trail[array_key_last($trail)]['url'] = null;

        return $trail;
    }

    /**
     * @return array<int, array{label: string, url: ?string}>
     */
    private static function pagesFor(Route $route): array
    {
        $productsIndex = ['label' => __('Products'), 'url' => route('admin.products.index')];
        $ordersIndex = ['label' => __('Orders'), 'url' => route('admin.orders.index')];
        $usersIndex = ['label' => __('Users'), 'url' => route('admin.users.index')];

        return match ($route->getName()) {
            'admin.dashboard' => [['label' => __('Dashboard'), 'url' => null]],
            'admin.orders.index' => [['label' => __('Orders'), 'url' => null]],
            'admin.orders.show' => [$ordersIndex, ['label' => __('Order :number', ['number' => self::parameter($route, 'order', Order::class)?->order_number ?? '']), 'url' => null]],
            'admin.products.index' => [['label' => __('Products'), 'url' => null]],
            'admin.products.create' => [$productsIndex, ['label' => __('New product'), 'url' => null]],
            'admin.products.edit' => [$productsIndex, ['label' => self::parameter($route, 'product', Product::class)?->name ?? __('Edit product'), 'url' => null]],
            'admin.inventory.index' => [['label' => __('Inventory'), 'url' => null]],
            'admin.categories.index' => [['label' => __('Categories'), 'url' => null]],
            'admin.brands.index' => [['label' => __('Brands'), 'url' => null]],
            'admin.attributes.index' => [['label' => __('Attributes'), 'url' => null]],
            'admin.reviews.index' => [['label' => __('Reviews'), 'url' => null]],
            'admin.coupons.index' => [['label' => __('Coupons'), 'url' => null]],
            'admin.users.index' => [['label' => __('Users'), 'url' => null]],
            'admin.customers.show' => [$usersIndex, ['label' => self::parameter($route, 'user', User::class)?->name ?? __('Customer'), 'url' => null]],
            'admin.roles.index' => [['label' => __('Roles'), 'url' => null]],
            'admin.settings.index' => [['label' => __('Settings'), 'url' => null]],
            'admin.activity.index' => [['label' => __('Activity log'), 'url' => null]],
            default => [],
        };
    }

    /**
     * The bound model for a route parameter, or null when it is not a model of the expected class.
     */
    private static function parameter(Route $route, string $key, string $class): ?object
    {
        $value = $route->parameter($key);

        return $value instanceof $class ? $value : null;
    }
}
