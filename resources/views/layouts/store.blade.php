<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')

        @isset($metaDescription)
            <meta name="description" content="{{ $metaDescription }}">
            <meta property="og:description" content="{{ $metaDescription }}">
        @endisset
        @isset($ogImage)
            <meta property="og:image" content="{{ $ogImage }}">
        @endisset
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:type" content="{{ $ogType ?? 'website' }}">
        <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
        @isset($structuredData)
            <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endisset
    </head>
    <body class="min-h-screen bg-white text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('Skip to content') }}</a>

        <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95" x-data="{ menu: false }">
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 lg:px-8">
                <button type="button" class="rounded-md p-2 hover:bg-zinc-100 lg:hidden dark:hover:bg-zinc-800" x-on:click="menu = !menu" :aria-expanded="menu" aria-controls="mobile-menu" aria-label="{{ __('Toggle menu') }}">
                    <flux:icon name="bars-2" class="size-5" />
                </button>

                <a href="{{ route('home') }}" wire:navigate class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</a>

                <nav aria-label="{{ __('Categories') }}" class="hidden items-center gap-5 text-sm lg:flex">
                    @foreach (app(\App\Services\StorefrontCatalog::class)->navigation() as $category)
                        <a href="{{ route('category', $category->slug) }}" wire:navigate class="hover:text-zinc-500 dark:hover:text-zinc-300">{{ $category->name }}</a>
                    @endforeach
                </nav>

                <div class="ms-auto flex flex-1 items-center justify-end gap-3 lg:flex-none">
                    <livewire:pages::store.search-box />

                    @auth
                        <a href="{{ route('dashboard') }}" class="hidden text-sm font-medium hover:underline sm:inline">{{ __('Account') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden text-sm font-medium hover:underline sm:inline">{{ __('Log in') }}</a>
                    @endauth
                </div>
            </div>

            <nav id="mobile-menu" x-show="menu" x-cloak class="border-t border-zinc-200 px-4 py-3 lg:hidden dark:border-zinc-800" aria-label="{{ __('Categories') }}">
                @foreach (app(\App\Services\StorefrontCatalog::class)->navigation() as $category)
                    <div class="py-1">
                        <a href="{{ route('category', $category->slug) }}" wire:navigate class="font-medium">{{ $category->name }}</a>
                        <div class="ms-4 flex flex-wrap gap-x-4 text-sm text-zinc-500">
                            @foreach ($category->children as $child)
                                <a href="{{ route('category', $child->slug) }}" wire:navigate>{{ $child->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </header>

        <main id="main" class="mx-auto w-full max-w-7xl px-4 py-8 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-sm text-zinc-500 sm:flex-row sm:justify-between lg:px-8">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('Prices include GST.') }}</p>
                <div class="flex gap-4">
                    <a href="{{ route('shop') }}" wire:navigate class="hover:underline">{{ __('All products') }}</a>
                    @can('reports.view')
                        <a href="{{ route('admin.home') }}" class="hover:underline">{{ __('Admin') }}</a>
                    @endcan
                </div>
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
