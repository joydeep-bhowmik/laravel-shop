<?php

use App\Services\StorefrontCatalog;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function sections(): array
    {
        return app(StorefrontCatalog::class)->home();
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::store')
            ->title(__('Shop online'));
    }
}; ?>

<div class="space-y-16">
    @php($banners = $this->sections['banners'])

    @if ($banners->isNotEmpty())
        <section aria-label="{{ __('Featured offers') }}" class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 lg:-mx-8 lg:px-8">
            @foreach ($banners as $banner)
                <a href="{{ $banner->cta_url ?: route('shop') }}" class="relative flex min-h-64 w-[88%] shrink-0 snap-center flex-col justify-end overflow-hidden rounded-2xl bg-zinc-900 p-8 text-white sm:w-[60%] lg:min-h-80 lg:w-[calc(50%-0.5rem)]">
                    @if ($banner->getFirstMediaUrl('image'))
                        <img src="{{ $banner->getFirstMediaUrl('image') }}" alt="" class="absolute inset-0 size-full object-cover opacity-70" loading="lazy">
                    @endif
                    <div class="relative space-y-2">
                        <h2 class="text-3xl font-semibold tracking-tight">{{ $banner->title }}</h2>
                        @if ($banner->subtitle)
                            <p class="max-w-md text-white/90">{{ $banner->subtitle }}</p>
                        @endif
                        @if ($banner->cta_label)
                            <span class="mt-3 inline-block rounded-md bg-white px-4 py-2 text-sm font-medium text-zinc-900">{{ $banner->cta_label }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </section>
    @else
        <section class="rounded-2xl bg-zinc-900 p-10 text-white">
            <h1 class="text-4xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <p class="mt-2 text-white/90">{{ __('Fresh arrivals, honest prices, delivered across India.') }}</p>
            <a href="{{ route('shop') }}" wire:navigate class="mt-6 inline-block rounded-md bg-white px-4 py-2 text-sm font-medium text-zinc-900">{{ __('Browse the shop') }}</a>
        </section>
    @endif

    @if ($this->sections['categories']->isNotEmpty())
        <section aria-labelledby="featured-categories" class="space-y-6">
            <h2 id="featured-categories" class="text-2xl font-semibold tracking-tight">{{ __('Shop by category') }}</h2>
            <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($this->sections['categories'] as $category)
                    <li>
                        <a href="{{ route('category', $category->slug) }}" wire:navigate class="flex h-24 items-center justify-center rounded-xl border border-zinc-200 text-center font-medium hover:border-zinc-900 dark:border-zinc-700 dark:hover:border-zinc-300">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($this->sections['deals']->isNotEmpty())
        <section aria-labelledby="deals" class="space-y-6 rounded-2xl bg-amber-50 p-6 dark:bg-amber-950/30">
            <h2 id="deals" class="text-2xl font-semibold tracking-tight">{{ __('Deals of the day') }}</h2>
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->sections['deals'] as $deal)
                    <li class="space-y-3">
                        <x-store.product-card :product="$deal" />
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-300"
                            x-data="{
                                end: new Date('{{ $deal->deal_ends_at->toIso8601String() }}').getTime(),
                                left: '',
                                tick() {
                                    const ms = this.end - Date.now();
                                    if (ms <= 0) { this.left = '{{ __('Deal ended') }}'; return; }
                                    const h = Math.floor(ms / 3600000), m = Math.floor(ms % 3600000 / 60000), s = Math.floor(ms % 60000 / 1000);
                                    this.left = `{{ __('Ends in') }} ${h}h ${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}s`;
                                }
                            }"
                            x-init="tick(); setInterval(() => tick(), 1000)"
                            x-text="left"
                            aria-live="off"
                        ></p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($this->sections['newArrivals']->isNotEmpty())
        <section aria-labelledby="new-arrivals" class="space-y-6">
            <div class="flex items-end justify-between">
                <h2 id="new-arrivals" class="text-2xl font-semibold tracking-tight">{{ __('New arrivals') }}</h2>
                <a href="{{ route('shop', ['sort' => 'newest']) }}" wire:navigate class="text-sm font-medium hover:underline">{{ __('View all') }}</a>
            </div>
            <ul class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                @foreach ($this->sections['newArrivals'] as $product)
                    <li><x-store.product-card :product="$product" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($this->sections['bestSellers']->isNotEmpty())
        <section aria-labelledby="best-sellers" class="space-y-6">
            <div class="flex items-end justify-between">
                <h2 id="best-sellers" class="text-2xl font-semibold tracking-tight">{{ __('Best sellers') }}</h2>
                <a href="{{ route('shop', ['sort' => 'popular']) }}" wire:navigate class="text-sm font-medium hover:underline">{{ __('View all') }}</a>
            </div>
            <ul class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                @foreach ($this->sections['bestSellers'] as $product)
                    <li><x-store.product-card :product="$product" /></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
