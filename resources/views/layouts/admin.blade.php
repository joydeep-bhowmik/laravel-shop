@php($darkMode = app(\App\Services\SettingsService::class)->bool('appearance.dark_mode'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($darkMode) x-data x-bind:class="$flux.dark ? 'dark' : ''" @endif>
    <head>
        @include('partials.head', ['darkMode' => $darkMode])
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('admin.home') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @can('reports.view')
                    <flux:sidebar.item icon="home" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                @endcan

                @can('orders.view')
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.orders.index')" :current="request()->routeIs('admin.orders.*')" wire:navigate>
                        {{ __('Orders') }}
                    </flux:sidebar.item>
                @endcan

                @can('products.view')
                    <flux:sidebar.group :heading="__('Catalog')" class="grid">
                        <flux:sidebar.item icon="shopping-bag" :href="route('admin.products.index')" :current="request()->routeIs('admin.products.*')" wire:navigate>
                            {{ __('Products') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="archive-box" :href="route('admin.inventory.index')" :current="request()->routeIs('admin.inventory.*')" wire:navigate>
                            {{ __('Inventory') }}
                        </flux:sidebar.item>
                        @can('catalog.manage')
                            <flux:sidebar.item icon="squares-2x2" :href="route('admin.categories.index')" :current="request()->routeIs('admin.categories.*')" wire:navigate>
                                {{ __('Categories') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="building-storefront" :href="route('admin.brands.index')" :current="request()->routeIs('admin.brands.*')" wire:navigate>
                                {{ __('Brands') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.attributes.index')" :current="request()->routeIs('admin.attributes.*')" wire:navigate>
                                {{ __('Attributes') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcan

                @canany(['reviews.moderate', 'settings.manage', 'activity.view'])
                    <flux:sidebar.group :heading="__('Administration')" class="grid">
                        @can('reviews.moderate')
                            <flux:sidebar.item icon="star" :href="route('admin.reviews.index')" :current="request()->routeIs('admin.reviews.*')" wire:navigate>
                                {{ __('Reviews') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('settings.manage')
                            <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings.index')" :current="request()->routeIs('admin.settings.*')" wire:navigate>
                                {{ __('Settings') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('activity.view')
                            <flux:sidebar.item icon="clock" :href="route('admin.activity.index')" :current="request()->routeIs('admin.activity.*')" wire:navigate>
                                {{ __('Activity log') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="arrow-uturn-left" :href="route('home')">
                    {{ __('Back to store') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            @if ($darkMode)
                <div class="hidden px-3 pb-2 lg:block"><x-theme-toggle /></div>
            @endif
            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            @if ($darkMode)
                <x-theme-toggle />
            @endif
            <x-desktop-user-menu />
        </flux:header>

        <flux:main>
            <div class="mx-auto w-full max-w-7xl">
                <x-admin.breadcrumbs />
                @if (filled($title ?? null))
                    <flux:heading size="xl" class="mb-6">{{ $title }}</flux:heading>
                @endif

                {{ $slot }}
            </div>
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
