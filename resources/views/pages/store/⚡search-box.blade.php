<?php

use App\Models\Product;
use App\Services\MoneyFormatter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $term = '';

    /**
     * Matching products for the autocomplete list. Needs at least two characters.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    #[Computed]
    public function results()
    {
        $term = trim($this->term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Product::search($term)
            ->query(fn ($query) => $query->published()->with('defaultVariant'))
            ->take(6)
            ->get();
    }

    public function submit()
    {
        return $this->redirectRoute('search', ['q' => trim($this->term)], navigate: true);
    }
}; ?>

<div class="relative w-full max-w-xs" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <form wire:submit="submit" role="search" class="relative">
        <label for="site-search" class="sr-only">{{ __('Search products') }}</label>
        <input
            id="site-search"
            type="search"
            wire:model.live.debounce.250ms="term"
            x-on:focus="open = true"
            x-on:input="open = true"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="site-search-results"
            :aria-expanded="open ? 'true' : 'false'"
            autocomplete="off"
            placeholder="{{ __('Search products') }}"
            class="w-full rounded-lg border border-zinc-300 bg-white py-2 ps-9 pe-3 text-sm focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/20 dark:border-zinc-600 dark:bg-zinc-800"
        >
        <flux:icon name="magnifying-glass" class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
    </form>

    <ul
        id="site-search-results"
        role="listbox"
        x-show="open && $wire.term.length >= 2"
        x-cloak
        class="absolute end-0 z-40 mt-2 w-80 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
    >
        @forelse ($this->results as $product)
            <li role="option">
                <a href="{{ route('product', $product->slug) }}" wire:navigate class="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-zinc-100 focus:bg-zinc-100 focus:outline-none dark:hover:bg-zinc-700">
                    <span class="truncate">{{ $product->name }}</span>
                    @if ($product->defaultVariant)
                        <span class="shrink-0 font-medium">{{ MoneyFormatter::format($product->defaultVariant->price_paise) }}</span>
                    @endif
                </a>
            </li>
        @empty
            <li class="px-3 py-2 text-sm text-zinc-500">{{ __('No products match') }}</li>
        @endforelse
        <li class="border-t border-zinc-200 dark:border-zinc-700">
            <a href="{{ route('search', ['q' => $term]) }}" wire:navigate class="block px-3 py-2 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-700">{{ __('See all results') }}</a>
        </li>
    </ul>
</div>
