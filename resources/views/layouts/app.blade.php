@php($darkMode = app(\App\Services\SettingsService::class)->bool('appearance.dark_mode'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($darkMode) x-data x-bind:class="$flux.dark ? 'dark' : ''" @endif>
    <head>
        @include('partials.head', ['darkMode' => $darkMode])

        @isset($metaDescription)
            <meta name="description" content="{{ $metaDescription }}">
        @endisset
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    </head>
    <body class="flex min-h-screen flex-col bg-white text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('Skip to content') }}</a>

        <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95" x-data="{ menu: false }">
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 lg:px-8">
                <button type="button" class="rounded-md p-2 hover:bg-zinc-100 lg:hidden dark:hover:bg-zinc-800"
                    x-on:click="menu = !menu" x-bind:aria-expanded="menu" aria-controls="mobile-menu" aria-label="{{ __('Open menu') }}">
                    <flux:icon name="bars-2" class="size-5" />
                </button>

                <a href="{{ route('home') }}" wire:navigate class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</a>

                <nav aria-label="{{ __('Categories') }}" class="hidden items-center gap-5 text-sm lg:flex">
                    @foreach (app(\App\Services\StorefrontCatalog::class)->navigation() as $category)
                        <a href="{{ route('category', ['category' => $category->slug]) }}" wire:navigate class="hover:text-zinc-500 dark:hover:text-zinc-300">{{ $category->name }}</a>
                    @endforeach
                </nav>

                <div class="ms-auto flex flex-1 items-center justify-end gap-4 lg:flex-none">
                    <livewire:store.search-box />

                    @if ($darkMode)
                        <x-theme-toggle />
                    @endif

                    @auth
                        <div class="relative hidden lg:block" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                            <button type="button" class="flex items-center gap-2 rounded-md px-2 py-1 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                x-on:click="open = !open" x-bind:aria-expanded="open" aria-haspopup="menu">
                                <flux:avatar :initials="auth()->user()->initials()" size="xs" />
                                {{ auth()->user()->name }}
                            </button>
                            <div x-show="open" x-cloak role="menu" class="absolute end-0 z-40 mt-2 w-56 rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                <a href="{{ route('dashboard') }}" wire:navigate role="menuitem" class="block px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">{{ __('My account') }}</a>
                                <a href="{{ route('account.profile') }}" wire:navigate role="menuitem" class="block px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">{{ __('Profile') }}</a>
                                <a href="{{ route('account.security') }}" wire:navigate role="menuitem" class="block px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">{{ __('Password and two-factor') }}</a>
                                @can('reports.view')
                                    <a href="{{ route('admin.home') }}" role="menuitem" class="block px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">{{ __('Admin panel') }}</a>
                                @endcan
                                <div class="my-1 border-t border-zinc-200 dark:border-zinc-700"></div>
                                <button type="button" role="menuitem" class="block w-full px-4 py-2 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700" x-on:click="$flux.modal('confirm-logout').show()">{{ __('Log out') }}</button>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="hidden text-sm font-medium hover:underline sm:inline">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" wire:navigate class="hidden rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700 sm:inline dark:bg-white dark:text-zinc-900">{{ __('Create account') }}</a>
                    @endauth
                </div>
            </div>

            <nav id="mobile-menu" x-show="menu" x-cloak class="space-y-3 border-t border-zinc-200 px-4 py-4 lg:hidden dark:border-zinc-800" aria-label="{{ __('Mobile menu') }}">
                @foreach (app(\App\Services\StorefrontCatalog::class)->navigation() as $category)
                    <div>
                        <a href="{{ route('category', ['category' => $category->slug]) }}" wire:navigate class="font-medium">{{ $category->name }}</a>
                        <div class="ms-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500">
                            @foreach ($category->children as $child)
                                <a href="{{ route('category', ['category' => $child->slug]) }}" wire:navigate>{{ $child->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="block py-1">{{ __('My account') }}</a>
                        <button type="button" class="block py-1 text-start" x-on:click="$flux.modal('confirm-logout').show()">{{ __('Log out') }}</button>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="block py-1">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" wire:navigate class="block py-1">{{ __('Create account') }}</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 text-sm lg:grid-cols-4 lg:px-8">
                <div class="space-y-2">
                    <p class="text-base font-semibold">{{ config('app.name') }}</p>
                    <p class="text-zinc-500">{{ __('Fresh arrivals, honest prices, delivered across India.') }}</p>
                </div>
                <nav aria-label="{{ __('Shop') }}" class="space-y-2">
                    <p class="font-medium">{{ __('Shop') }}</p>
                    <a href="{{ route('shop') }}" wire:navigate class="block text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('All products') }}</a>
                    <a href="{{ route('search') }}" wire:navigate class="block text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('Search') }}</a>
                </nav>
                <nav aria-label="{{ __('Account') }}" class="space-y-2">
                    <p class="font-medium">{{ __('Account') }}</p>
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="block text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('My account') }}</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="block text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" wire:navigate class="block text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('Create account') }}</a>
                    @endauth
                </nav>
                <p class="text-zinc-500 lg:text-end">&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('Prices include GST.') }}</p>
            </div>
        </footer>

        <x-confirm-logout />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
