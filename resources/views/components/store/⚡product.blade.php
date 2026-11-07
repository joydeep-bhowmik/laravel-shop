<?php

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\DeliveryService;
use App\Services\MoneyFormatter;
use App\Services\ProductCatalogService;
use App\Services\ReviewService;
use App\Services\StorefrontCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Product $product;

    public ?int $variantId = null;

    /** Selected option value per attribute, keyed by attribute id. @var array<int, int> */
    public array $optionValueIds = [];

    public string $pincode = '';

    /** @var array<string, mixed>|null */
    public ?array $delivery = null;

    public int $reviewRating = 5;

    public string $reviewTitle = '';

    public string $reviewBody = '';

    public function mount(int $productId, StorefrontCatalog $catalog): void
    {
        $this->product = Product::query()
            ->published()
            ->whereKey($productId)
            ->with(['category.parent', 'brand', 'variants.attributeValues.attribute', 'media'])
            ->firstOrFail();

        $default = $this->product->variants->firstWhere('is_default', true)
            ?? $this->activeVariants->first();

        if ($default !== null) {
            $this->selectVariant($default->id);
        }

        $catalog->rememberView($this->product);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProductVariant>
     */
    #[Computed]
    public function activeVariants()
    {
        return $this->product->variants->where('is_active', true)->values();
    }

    #[Computed]
    public function variant(): ?ProductVariant
    {
        return $this->variantId === null ? null : $this->activeVariants->firstWhere('id', $this->variantId);
    }

    /**
     * Option groups (e.g. Size, Colour) built from the active variants.
     *
     * @return \Illuminate\Support\Collection<int, array{attribute: Attribute, values: array<int, \App\Models\AttributeValue>}>
     */
    #[Computed]
    public function optionGroups()
    {
        $groups = [];

        foreach ($this->activeVariants as $variant) {
            foreach ($variant->attributeValues as $value) {
                if (! $value->attribute->is_variant) {
                    continue;
                }

                $groups[$value->attribute->id]['attribute'] = $value->attribute;
                $groups[$value->attribute->id]['values'][$value->id] = $value;
            }
        }

        return collect($groups)
            ->sortBy(fn (array $group) => $group['attribute']->position)
            ->map(fn (array $group) => ['attribute' => $group['attribute'], 'values' => array_values($group['values'])])
            ->values();
    }

    #[Computed]
    public function galleryUrls(): array
    {
        $variantImages = $this->variant?->getMedia('images') ?? collect();

        $media = $variantImages->isNotEmpty() ? $variantImages : $this->product->getMedia('gallery');

        return $media->isEmpty()
            ? [asset('images/placeholder-product.svg')]
            : $media->map(fn ($item) => $item->getUrl('detail'))->values()->all();
    }

    #[Computed]
    public function stockState(): array
    {
        $variant = $this->variant;

        if ($variant === null) {
            return ['label' => __('This combination is not available'), 'tone' => 'text-zinc-500', 'purchasable' => false];
        }

        if ($variant->stock_quantity === 0) {
            return ['label' => __('Out of stock'), 'tone' => 'text-red-600', 'purchasable' => false];
        }

        if ($variant->isLowStock()) {
            return ['label' => __('Only :n left', ['n' => $variant->stock_quantity]), 'tone' => 'text-amber-600', 'purchasable' => true];
        }

        return ['label' => __('In stock'), 'tone' => 'text-green-700', 'purchasable' => true];
    }

    #[Computed]
    public function reviews()
    {
        return $this->product->reviews()->approved()->with('user')->latest()->limit(10)->get();
    }

    #[Computed]
    public function related()
    {
        return app(StorefrontCatalog::class)->related($this->product);
    }

    #[Computed]
    public function recentlyViewed()
    {
        return app(StorefrontCatalog::class)->recentlyViewed($this->product);
    }

    #[Computed]
    public function alreadyReviewed(): bool
    {
        return Auth::check() && $this->product->reviews()->where('user_id', Auth::id())->exists();
    }

    /**
     * Pick a variant directly, used when a product has variants without option groups.
     */
    public function selectVariant(int $variantId): void
    {
        $variant = $this->activeVariants->firstWhere('id', $variantId);

        if ($variant === null) {
            return;
        }

        $this->variantId = $variant->id;
        $this->optionValueIds = $variant->attributeValues
            ->filter(fn ($value) => $value->attribute->is_variant)
            ->mapWithKeys(fn ($value) => [$value->attribute_id => $value->id])
            ->all();

        unset($this->variant, $this->galleryUrls, $this->stockState);
    }

    /**
     * Choose an option value, then switch to the variant that has every selected option.
     */
    public function chooseOption(int $attributeId, int $valueId): void
    {
        $this->optionValueIds[$attributeId] = $valueId;

        $match = $this->activeVariants->first(function (ProductVariant $variant): bool {
            $variantValues = $variant->attributeValues->pluck('id')->all();

            foreach ($this->optionValueIds as $selected) {
                if (! in_array($selected, $variantValues, true)) {
                    return false;
                }
            }

            return true;
        });

        $this->variantId = $match?->id;
        unset($this->variant, $this->galleryUrls, $this->stockState);
    }

    public function checkPincode(): void
    {
        $this->validate(['pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/']], [
            'pincode.regex' => __('Enter a valid 6-digit pincode.'),
        ]);

        $this->delivery = app(DeliveryService::class)->check($this->pincode);

        if ($this->delivery === null) {
            $this->addError('pincode', __('We could not find that pincode.'));
        }
    }

    public function submitReview(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $validated = $this->validate([
            'reviewRating' => ['required', 'integer', 'between:1,5'],
            'reviewTitle' => ['nullable', 'string', 'max:120'],
            'reviewBody' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            app(ReviewService::class)->submit(
                Auth::user(),
                $this->product,
                $validated['reviewRating'],
                $validated['reviewTitle'] ?: null,
                $validated['reviewBody'] ?: null,
            );
        } catch (\InvalidArgumentException $exception) {
            $this->addError('reviewRating', $exception->getMessage());

            return;
        }

        $this->reset('reviewTitle', 'reviewBody');
        $this->reviewRating = 5;
        unset($this->alreadyReviewed);

        \Flux\Flux::toast(variant: 'success', text: __('Thanks. Your review will appear once approved.'));
    }

    /**
     * Schema.org Product data for the variant currently selected.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function structuredData(): array
    {
        $variant = $this->variant;
        $description = $this->product->meta_description ?: Str::limit(strip_tags((string) ($this->product->short_description ?: $this->product->description)), 155);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->product->name,
            'description' => $description,
            'sku' => $variant?->sku,
            'brand' => $this->product->brand ? ['@type' => 'Brand', 'name' => $this->product->brand->name] : null,
            'image' => $this->galleryUrls,
            'offers' => $variant === null ? null : [
                '@type' => 'Offer',
                'priceCurrency' => 'INR',
                'price' => number_format($variant->price_paise / 100, 2, '.', ''),
                'availability' => $variant->stock_quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => route('product', ['product' => $this->product->slug]),
            ],
            'aggregateRating' => $this->product->rating_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $this->product->rating_average,
                'reviewCount' => $this->product->rating_count,
            ] : null,
        ], fn ($value) => $value !== null);
    }
}; ?>

<div class="space-y-16">
    <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-zinc-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:underline">{{ __('Home') }}</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('category', ['category' => $product->category->slug]) }}" wire:navigate class="hover:underline">{{ $product->category->name }}</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-zinc-800 dark:text-zinc-200">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="grid gap-10 lg:grid-cols-2">
        <section aria-label="{{ __('Product images') }}" wire:key="gallery-{{ $variantId }}" x-data="{ active: 0, zoom: false, origin: '50% 50%' }" class="space-y-4">
            <div class="relative aspect-square overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-800"
                x-on:mouseenter="zoom = true"
                x-on:mouseleave="zoom = false"
                x-on:mousemove="const r = $el.getBoundingClientRect(); origin = `${((($event.clientX - r.left) / r.width) * 100).toFixed(1)}% ${((($event.clientY - r.top) / r.height) * 100).toFixed(1)}%`"
            >
                @foreach ($this->galleryUrls as $index => $url)
                    <img
                        src="{{ $url }}"
                        alt="{{ $product->name }} {{ $index + 1 }}"
                        x-show="active === {{ $index }}"
                        x-bind:style="zoom && active === {{ $index }} ? 'transform: scale(2); transform-origin: ' + origin : ''"
                        class="absolute inset-0 size-full object-cover transition-transform duration-200"
                        @if ($index > 0) loading="lazy" @endif
                        width="1000" height="1000"
                    >
                @endforeach
            </div>

            @if (count($this->galleryUrls) > 1)
                <div class="flex gap-2 overflow-x-auto" role="group" aria-label="{{ __('Choose an image') }}">
                    @foreach ($this->galleryUrls as $index => $url)
                        <button type="button" x-on:click="active = {{ $index }}" x-bind:aria-pressed="active === {{ $index }}" class="size-20 shrink-0 overflow-hidden rounded-md border-2 focus:outline-none focus-visible:ring-2" x-bind:class="active === {{ $index }} ? 'border-zinc-900 dark:border-white' : 'border-transparent'">
                            <img src="{{ $url }}" alt="" class="size-full object-cover" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-6" aria-labelledby="product-title">
            <div class="space-y-2">
                @if ($product->brand)
                    <p class="text-sm font-medium uppercase tracking-wide text-zinc-500"><a href="{{ route('brand', ['brand' => $product->brand->slug]) }}" wire:navigate class="hover:underline">{{ $product->brand->name }}</a></p>
                @endif
                <h1 id="product-title" class="text-3xl font-semibold tracking-tight">{{ $product->name }}</h1>

                @if ($product->rating_count > 0)
                    <p class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <flux:icon name="star" variant="solid" class="size-4 text-amber-500" />
                        {{ __(':avg out of 5 from :count reviews', ['avg' => number_format((float) $product->rating_average, 1), 'count' => $product->rating_count]) }}
                    </p>
                @endif
            </div>

            @if ($this->variant)
                <div class="flex flex-wrap items-baseline gap-3">
                    <span class="text-3xl font-semibold" aria-live="polite">{{ MoneyFormatter::format($this->variant->price_paise) }}</span>
                    @if ($this->variant->compare_at_price_paise && $this->variant->compare_at_price_paise > $this->variant->price_paise)
                        <span class="text-lg text-zinc-500 line-through">{{ MoneyFormatter::format($this->variant->compare_at_price_paise) }}</span>
                    @endif
                    <span class="text-sm text-zinc-500">{{ __('Inclusive of GST') }}</span>
                </div>
            @endif

            @if ($product->type === ProductType::Variable && $this->optionGroups->isNotEmpty())
                <div class="space-y-4">
                    @foreach ($this->optionGroups as $group)
                        <fieldset class="space-y-2">
                            <legend class="text-sm font-medium">{{ $group['attribute']->name }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($group['values'] as $value)
                                    @php($selected = ($optionValueIds[$group['attribute']->id] ?? null) === $value->id)
                                    <button type="button"
                                        wire:click="chooseOption({{ $group['attribute']->id }}, {{ $value->id }})"
                                        aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                        class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm focus:outline-none focus-visible:ring-2 {{ $selected ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 hover:border-zinc-900 dark:border-zinc-600' }}">
                                        @if ($value->color_hex)
                                            <span class="inline-block size-3 rounded-full border border-zinc-400" style="background-color: {{ $value->color_hex }}" aria-hidden="true"></span>
                                        @endif
                                        {{ $value->value }}
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            @elseif ($this->activeVariants->count() > 1)
                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">{{ __('Choose an option') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->activeVariants as $option)
                            <button type="button" wire:click="selectVariant({{ $option->id }})" aria-pressed="{{ $variantId === $option->id ? 'true' : 'false' }}"
                                class="rounded-md border px-3 py-2 text-sm {{ $variantId === $option->id ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 dark:border-zinc-600' }}">
                                {{ $option->name ?: $option->sku }} · {{ MoneyFormatter::format($option->price_paise) }}
                            </button>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <p class="text-sm font-medium {{ $this->stockState['tone'] }}" role="status">{{ $this->stockState['label'] }}</p>

            @if ($this->variant)
                <p class="text-sm text-zinc-500">{{ __('SKU :sku', ['sku' => $this->variant->sku]) }}</p>
            @endif

            <section aria-labelledby="delivery-check" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <h2 id="delivery-check" class="font-medium">{{ __('Check delivery') }}</h2>
                <form wire:submit="checkPincode" class="flex items-start gap-2">
                    <div class="flex-1">
                        <label for="pincode" class="sr-only">{{ __('Pincode') }}</label>
                        <input id="pincode" type="text" inputmode="numeric" maxlength="6" wire:model="pincode" placeholder="{{ __('Pincode') }}" class="w-full rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        @error('pincode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded-md border border-zinc-900 px-4 py-2 text-sm font-medium hover:bg-zinc-900 hover:text-white dark:border-white dark:hover:bg-white dark:hover:text-zinc-900">{{ __('Check') }}</button>
                </form>

                @if ($delivery)
                    <div class="text-sm" role="status">
                        @if ($delivery['serviceable'])
                            <p class="text-green-700 dark:text-green-400">{{ __('Delivers to :city, :state by :date.', ['city' => $delivery['city'], 'state' => $delivery['state'], 'date' => $delivery['estimated_on']]) }}</p>
                            <p class="text-zinc-500">{{ $delivery['cod'] ? __('Cash on delivery available.') : __('Cash on delivery is not available here.') }}</p>
                        @else
                            <p class="text-red-600">{{ __('Sorry, we do not deliver to this pincode yet.') }}</p>
                        @endif
                    </div>
                @endif
            </section>

            <div class="prose prose-zinc max-w-none text-sm dark:prose-invert">
                {!! nl2br(e($product->description)) !!}
            </div>
        </section>
    </div>

    <section aria-labelledby="reviews" class="grid gap-10 lg:grid-cols-[1fr_1.2fr]">
        <div class="space-y-4">
            <h2 id="reviews" class="text-2xl font-semibold tracking-tight">{{ __('Ratings and reviews') }}</h2>

            @if (! $this->alreadyReviewed)
                <form wire:submit="submitReview" class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <h3 class="font-medium">{{ __('Write a review') }}</h3>
                    @guest
                        <p class="text-sm text-zinc-500">
                            <a href="{{ route('login') }}" wire:navigate class="underline">{{ __('Log in') }}</a> {{ __('to share your review.') }}
                        </p>
                    @endguest
                    <div>
                        <label for="review-rating" class="text-sm font-medium">{{ __('Rating') }}</label>
                        <select id="review-rating" wire:model="reviewRating" class="mt-1 rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                            @for ($stars = 5; $stars >= 1; $stars--)
                                <option value="{{ $stars }}">{{ trans_choice(':count star|:count stars', $stars, ['count' => $stars]) }}</option>
                            @endfor
                        </select>
                        @error('reviewRating') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="review-title" class="text-sm font-medium">{{ __('Title') }}</label>
                        <input id="review-title" type="text" wire:model="reviewTitle" class="mt-1 w-full rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                    </div>
                    <div>
                        <label for="review-body" class="text-sm font-medium">{{ __('Review') }}</label>
                        <textarea id="review-body" rows="4" wire:model="reviewBody" class="mt-1 w-full rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800"></textarea>
                        @error('reviewBody') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">{{ __('Submit review') }}</button>
                </form>
            @endif
        </div>

        <ul class="space-y-6">
            @forelse ($this->reviews as $review)
                <li class="space-y-1 border-b border-zinc-200 pb-6 dark:border-zinc-800">
                    <p class="flex items-center gap-2 text-sm">
                        <span class="font-medium">{{ $review->user->name }}</span>
                        <span class="text-amber-600">{{ str_repeat('★', $review->rating) }}</span>
                    </p>
                    @if ($review->title)<p class="font-medium">{{ $review->title }}</p>@endif
                    @if ($review->body)<p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $review->body }}</p>@endif
                    <p class="text-xs text-zinc-500">{{ $review->created_at->format('d M Y') }}</p>
                </li>
            @empty
                <li class="text-sm text-zinc-500">{{ __('No reviews yet. Be the first to review this product.') }}</li>
            @endforelse
        </ul>
    </section>

    @if ($this->related->isNotEmpty())
        <section aria-labelledby="related" class="space-y-6">
            <h2 id="related" class="text-2xl font-semibold tracking-tight">{{ __('You may also like') }}</h2>
            <ul class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                @foreach ($this->related as $item)
                    <li><x-store.product-card :product="$item" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($this->recentlyViewed->isNotEmpty())
        <section aria-labelledby="recent" class="space-y-6">
            <h2 id="recent" class="text-2xl font-semibold tracking-tight">{{ __('Recently viewed') }}</h2>
            <ul class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                @foreach ($this->recentlyViewed as $item)
                    <li><x-store.product-card :product="$item" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    <script type="application/ld+json">
        {!! json_encode($this->structuredData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}
    </script>
</div>
