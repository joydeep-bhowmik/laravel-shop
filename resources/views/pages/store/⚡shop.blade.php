<?php

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Services\MoneyFormatter;
use App\Services\ProductListing;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /** Fixed by the /category/{slug} route. */
    public ?string $categorySlug = null;

    /** Fixed by the /brand/{slug} route. */
    public ?string $brandSlug = null;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    /** @var array<int, string> */
    #[Url(as: 'brand', except: [])]
    public array $brandIds = [];

    /** @var array<int, string> */
    #[Url(as: 'attr', except: [])]
    public array $valueIds = [];

    #[Url(as: 'min', except: '')]
    public string $minRupees = '';

    #[Url(as: 'max', except: '')]
    public string $maxRupees = '';

    #[Url(as: 'rating', except: 0)]
    public int $minRating = 0;

    #[Url(as: 'stock', except: false)]
    public bool $inStockOnly = false;

    public function mount(?string $categorySlug = null, ?string $brandSlug = null): void
    {
        $this->categorySlug = $categorySlug;
        $this->brandSlug = $brandSlug;
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function category(): ?Category
    {
        return $this->categorySlug === null ? null : Category::query()->where('slug', $this->categorySlug)->active()->firstOrFail();
    }

    #[Computed]
    public function brand(): ?Brand
    {
        return $this->brandSlug === null ? null : Brand::query()->where('slug', $this->brandSlug)->active()->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function filters(): array
    {
        $brandIds = array_map('intval', $this->brandIds);

        if ($this->brand !== null) {
            $brandIds = [$this->brand->id];
        }

        return [
            'q' => $this->q,
            'category_id' => $this->category?->id,
            'brand_ids' => $brandIds,
            'attribute_value_ids' => array_map('intval', $this->valueIds),
            'min_price' => $this->paiseOrNull($this->minRupees),
            'max_price' => $this->paiseOrNull($this->maxRupees),
            'min_rating' => $this->minRating ?: null,
            'in_stock' => $this->inStockOnly,
            'sort' => in_array($this->sort, ProductListing::SORTS, true) ? $this->sort : 'newest',
        ];
    }

    #[Computed]
    public function products()
    {
        return app(ProductListing::class)->build($this->filters)->paginate(12);
    }

    #[Computed]
    public function categories()
    {
        if ($this->category !== null) {
            return $this->category->children()->active()->get();
        }

        return Category::query()->active()->whereNull('parent_id')->orderBy('position')->get();
    }

    #[Computed]
    public function brands()
    {
        return Brand::query()->active()->orderBy('name')->get();
    }

    /**
     * Attributes with their values, offered as checkbox facets.
     */
    #[Computed]
    public function facets()
    {
        return Attribute::query()->with('values')->whereHas('values')->orderBy('position')->orderBy('name')->get();
    }

    public function resetFilters(): void
    {
        $this->reset('brandIds', 'valueIds', 'minRupees', 'maxRupees', 'minRating', 'inStockOnly', 'q');
    }

    public function pageTitle(): string
    {
        return $this->category?->name ?? $this->brand?->name ?? __('Shop');
    }

    private function paiseOrNull(string $rupees): ?int
    {
        if (trim($rupees) === '') {
            return null;
        }

        try {
            return MoneyFormatter::toPaise($rupees);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function render()
    {
        $description = $this->category?->meta_description
            ?? $this->category?->description
            ?? __('Shop :name online with cash on delivery and easy returns.', ['name' => $this->pageTitle()]);

        return $this->view()
            ->layout('layouts::store', [
                'metaDescription' => \Illuminate\Support\Str::limit((string) $description, 155),
                'canonical' => $this->categorySlug || $this->brandSlug ? url()->current() : route('shop'),
            ])
            ->title($this->pageTitle());
    }
}; ?>

<div class="space-y-6">
    <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-zinc-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:underline">{{ __('Home') }}</a></li>
            <li aria-hidden="true">/</li>
            @if ($this->category?->parent)
                <li><a href="{{ route('category', $this->category->parent->slug) }}" wire:navigate class="hover:underline">{{ $this->category->parent->name }}</a></li>
                <li aria-hidden="true">/</li>
            @endif
            <li aria-current="page" class="text-zinc-800 dark:text-zinc-200">{{ $this->pageTitle() }}</li>
        </ol>
    </nav>

    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $this->pageTitle() }}</h1>
            @if ($this->category?->description)
                <p class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">{{ $this->category->description }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <label for="sort" class="text-sm text-zinc-500">{{ __('Sort by') }}</label>
            <select id="sort" wire:model.live="sort" class="rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                <option value="newest">{{ __('Newest') }}</option>
                <option value="price_asc">{{ __('Price: low to high') }}</option>
                <option value="price_desc">{{ __('Price: high to low') }}</option>
                <option value="rating">{{ __('Top rated') }}</option>
                <option value="popular">{{ __('Most reviewed') }}</option>
            </select>
        </div>
    </header>

    <div class="grid gap-8 lg:grid-cols-[16rem_1fr]">
        <aside aria-label="{{ __('Filters') }}" class="space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">{{ __('Filters') }}</h2>
                <button type="button" wire:click="resetFilters" class="text-sm text-zinc-500 underline-offset-2 hover:underline">{{ __('Clear all') }}</button>
            </div>

            @if ($this->category === null && $this->categories->isNotEmpty())
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-medium">{{ __('Categories') }}</legend>
                    <ul class="space-y-1 text-sm">
                        @foreach ($this->categories as $category)
                            <li><a href="{{ route('category', $category->slug) }}" wire:navigate class="hover:underline">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </fieldset>
            @elseif ($this->category !== null && $this->categories->isNotEmpty())
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-medium">{{ __('Subcategories') }}</legend>
                    <ul class="space-y-1 text-sm">
                        @foreach ($this->categories as $category)
                            <li><a href="{{ route('category', $category->slug) }}" wire:navigate class="hover:underline">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </fieldset>
            @endif

            <fieldset class="space-y-2">
                <legend class="mb-2 text-sm font-medium">{{ __('Price (₹)') }}</legend>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" min="0" inputmode="decimal" placeholder="{{ __('Min') }}" aria-label="{{ __('Minimum price') }}" wire:model.live.debounce.500ms="minRupees" class="rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                    <input type="number" min="0" inputmode="decimal" placeholder="{{ __('Max') }}" aria-label="{{ __('Maximum price') }}" wire:model.live.debounce.500ms="maxRupees" class="rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                </div>
            </fieldset>

            @if ($this->brand === null && $this->brands->isNotEmpty())
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-medium">{{ __('Brand') }}</legend>
                    @foreach ($this->brands as $brand)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" value="{{ $brand->id }}" wire:model.live="brandIds" class="rounded border-zinc-300 dark:border-zinc-600">
                            {{ $brand->name }}
                        </label>
                    @endforeach
                </fieldset>
            @endif

            @foreach ($this->facets as $attribute)
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-medium">{{ $attribute->name }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($attribute->values as $value)
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-zinc-300 px-3 py-1 text-sm has-[:checked]:border-zinc-900 has-[:checked]:bg-zinc-900 has-[:checked]:text-white dark:border-zinc-600 dark:has-[:checked]:border-white dark:has-[:checked]:bg-white dark:has-[:checked]:text-zinc-900">
                                <input type="checkbox" value="{{ $value->id }}" wire:model.live="valueIds" class="sr-only">
                                @if ($value->color_hex)
                                    <span class="inline-block size-3 rounded-full border border-zinc-400" style="background-color: {{ $value->color_hex }}" aria-hidden="true"></span>
                                @endif
                                {{ $value->value }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <fieldset class="space-y-2">
                <legend class="mb-2 text-sm font-medium">{{ __('Customer rating') }}</legend>
                @foreach ([4 => __('4 stars and up'), 3 => __('3 stars and up'), 0 => __('Any rating')] as $stars => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="rating" value="{{ $stars }}" wire:model.live="minRating" class="border-zinc-300 dark:border-zinc-600">
                        {{ $label }}
                    </label>
                @endforeach
            </fieldset>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="inStockOnly" class="rounded border-zinc-300 dark:border-zinc-600">
                {{ __('In stock only') }}
            </label>
        </aside>

        <section aria-live="polite" aria-label="{{ __('Products') }}" class="space-y-6">
            <p class="text-sm text-zinc-500" wire:loading.class="opacity-50">
                {{ trans_choice(':count product|:count products', $this->products->total(), ['count' => $this->products->total()]) }}
            </p>

            @if ($this->products->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
                    <p class="font-medium">{{ __('No products match these filters.') }}</p>
                    <button type="button" wire:click="resetFilters" class="mt-3 text-sm underline underline-offset-2">{{ __('Clear filters') }}</button>
                </div>
            @else
                <ul class="grid grid-cols-2 gap-6 md:grid-cols-3" wire:loading.class="opacity-60">
                    @foreach ($this->products as $product)
                        <li><x-store.product-card :product="$product" /></li>
                    @endforeach
                </ul>

                <div>{{ $this->products->links() }}</div>
            @endif
        </section>
    </div>
</div>
