<?php


use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\MoneyFormatter;
use App\Services\ProductCatalogService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

new class extends Component {
    use WithFileUploads;

    public ?int $productId = null;

    public string $name = '';

    public ?int $category_id = null;

    public ?int $brand_id = null;

    public string $type = 'simple';

    public string $status = 'draft';

    public string $scheduled_for = '';

    public string $deal_ends_at = '';

    public string $short_description = '';

    public string $description = '';

    public string $meta_title = '';

    public string $meta_description = '';

    public string $hsn_code = '';

    public int $gst_rate = 18;

    /** @var array<int, int|string> */
    public array $attributeValueIds = [];

    /**
     * Each row: key, id, sku, name, price, compare, stock, threshold, weight, is_default, attributeValueIds.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $variants = [];

    /** Files picked in the gallery picker, moved into pendingImages as they arrive. @var array<int, mixed> */
    public array $uploads = [];

    /** Images chosen but not saved yet, keyed like the gallery order. @var array<string, mixed> */
    public array $pendingImages = [];

    /** Gallery order: "m{mediaId}" for saved images, "p{random}" for pending ones. @var array<int, string> */
    public array $galleryOrder = [];

    /** Files picked per variant row, moved into variantPending as they arrive. @var array<string, array<int, mixed>> */
    public array $variantUploads = [];

    /** Variant images chosen but not saved yet: rowKey => (pendingKey => file). @var array<string, array<string, mixed>> */
    public array $variantPending = [];

    /** Variant image order: rowKey => keys ("m{mediaId}" saved, "p{random}" pending). @var array<string, array<int, string>> */
    public array $variantOrder = [];

    public function mount(?int $product = null): void
    {
        if ($product === null) {
            Gate::authorize('products.create');

            $this->variants = [$this->blankVariant(isDefault: true)];

            return;
        }

        Gate::authorize('products.update');

        $record = Product::query()->with(['variants.attributeValues', 'attributeValues'])->findOrFail($product);

        $this->productId = $record->id;
        $this->name = $record->name;
        $this->category_id = $record->category_id;
        $this->brand_id = $record->brand_id;
        $this->type = $record->type->value;
        $this->status = $record->status->value;
        $this->scheduled_for = $record->scheduled_for?->format('Y-m-d\TH:i') ?? '';
        $this->deal_ends_at = $record->deal_ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->short_description = (string) $record->short_description;
        $this->description = (string) $record->description;
        $this->meta_title = (string) $record->meta_title;
        $this->meta_description = (string) $record->meta_description;
        $this->hsn_code = (string) $record->hsn_code;
        $this->gst_rate = $record->gst_rate;
        $this->attributeValueIds = $record->attributeValues->pluck('id')->all();
        $this->galleryOrder = $this->savedGalleryKeys($record);
        $this->variantOrder = $record->variants
            ->mapWithKeys(fn (ProductVariant $variant) => [(string) $variant->id => $this->savedImageKeys($variant)])
            ->all();

        $this->variants = $record->variants->map(fn (ProductVariant $variant): array => [
            'key' => (string) $variant->id,
            'id' => $variant->id,
            'sku' => $variant->sku,
            'name' => (string) $variant->name,
            'price' => number_format($variant->price_paise / 100, 2, '.', ''),
            'compare' => $variant->compare_at_price_paise ? number_format($variant->compare_at_price_paise / 100, 2, '.', '') : '',
            'stock' => (string) $variant->stock_quantity,
            'threshold' => $variant->low_stock_threshold,
            'weight' => (string) $variant->weight_grams,
            'is_default' => $variant->is_default,
            'attributeValueIds' => $variant->attributeValues->pluck('id')->all(),
        ])->values()->all();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get();
    }

    #[Computed]
    public function brands()
    {
        return Brand::query()->orderBy('name')->get();
    }

    #[Computed]
    public function variantAttributeOptions()
    {
        return AttributeValue::query()
            ->with('attribute')
            ->whereHas('attribute', fn ($query) => $query->where('is_variant', true))
            ->get()
            ->groupBy(fn (AttributeValue $value): string => $value->attribute->name);
    }

    #[Computed]
    public function descriptiveAttributeOptions()
    {
        return AttributeValue::query()
            ->with('attribute')
            ->whereHas('attribute', fn ($query) => $query->where('is_variant', false))
            ->get()
            ->groupBy(fn (AttributeValue $value): string => $value->attribute->name);
    }

    /**
     * Images in display order, saved and pending together.
     *
     * @return array<int, array{key: string, src: string, pending: bool, id: ?int}>
     */
    #[Computed]
    public function gallery(): array
    {
        $saved = $this->productId === null
            ? collect()
            : Product::query()->findOrFail($this->productId)->getMedia('gallery')->keyBy(fn ($media) => 'm'.$media->id);

        return collect($this->galleryOrder)
            ->map(function (string $key) use ($saved): ?array {
                if (str_starts_with($key, 'p')) {
                    $file = $this->pendingImages[$key] ?? null;

                    return $file === null ? null : ['key' => $key, 'src' => $file->temporaryUrl(), 'pending' => true, 'id' => null];
                }

                $media = $saved->get($key);

                return $media === null ? null : ['key' => $key, 'src' => $media->getUrl('thumb'), 'pending' => false, 'id' => $media->id];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function addVariant(): void
    {
        $this->variants[] = $this->blankVariant(isDefault: false);
    }

    public function removeVariant(int $index): void
    {
        if (count($this->variants) === 1) {
            return;
        }

        $rowKey = $this->variants[$index]['key'];
        unset($this->variantUploads[$rowKey], $this->variantPending[$rowKey], $this->variantOrder[$rowKey]);
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function makeDefault(int $index): void
    {
        foreach ($this->variants as $position => $variant) {
            $this->variants[$position]['is_default'] = $position === $index;
        }
    }

    public function save(): void
    {
        Gate::authorize($this->productId === null ? 'products.create' : 'products.update');

        $this->validate();

        $data = [
            'name' => $this->name,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'type' => $this->type,
            'status' => $this->status,
            'scheduled_for' => $this->scheduled_for ?: null,
            'deal_ends_at' => $this->deal_ends_at ?: null,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'hsn_code' => $this->hsn_code,
            'gst_rate' => $this->gst_rate,
            'attribute_value_ids' => array_map('intval', $this->attributeValueIds),
            'variants' => array_map(fn (array $variant): array => [
                'id' => $variant['id'],
                'sku' => trim($variant['sku']),
                'name' => $variant['name'] ?: null,
                'price_paise' => MoneyFormatter::toPaise((string) $variant['price']),
                'compare_at_price_paise' => $variant['compare'] !== '' ? MoneyFormatter::toPaise((string) $variant['compare']) : null,
                'stock_quantity' => $variant['id'] === null ? (int) $variant['stock'] : 0,
                'low_stock_threshold' => (int) $variant['threshold'],
                'weight_grams' => $variant['weight'] !== '' ? (int) $variant['weight'] : null,
                'is_default' => (bool) $variant['is_default'],
                'attribute_value_ids' => array_map('intval', $variant['attributeValueIds']),
            ], $this->variants),
        ];

        $product = $this->productId === null ? null : Product::query()->findOrFail($this->productId);

        try {
            $saved = app(ProductCatalogService::class)->save($product, $data);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->storeUploads($saved);

        if ($this->productId === null) {
            $this->redirectRoute('admin.products.edit', ['product' => $saved->id], navigate: true);

            return;
        }

        $this->mount($saved->id);
        $this->pendingImages = [];
        $this->uploads = [];
        $this->variantUploads = [];
        $this->variantPending = [];
        unset($this->gallery);

        Flux::toast(variant: 'success', text: __('Product saved.'));
    }

    /**
     * Pick up files from the gallery picker. They join the gallery as pending images until the product is saved.
     */
    public function updatedUploads(): void
    {
        $this->validate(['uploads.*' => ['image', 'max:5120']]);

        foreach ($this->uploads as $file) {
            $key = 'p'.Str::random(8);
            $this->pendingImages[$key] = $file;
            $this->galleryOrder[] = $key;
        }

        $this->reset('uploads');
        unset($this->gallery);
    }

    /**
     * Drag and drop reorder from wire:sort. The order is written when the product is saved.
     */
    public function reorderGallery(string $key, int $position): void
    {
        $order = array_values(array_filter($this->galleryOrder, fn (string $item): bool => $item !== $key));
        array_splice($order, $position, 0, [$key]);

        $this->galleryOrder = $order;
        unset($this->gallery);
    }

    /**
     * Remove an image: pending ones are dropped, saved ones are deleted from the product.
     */
    public function removeImage(string $key): void
    {
        if (str_starts_with($key, 'm')) {
            Gate::authorize('products.update');
            Product::query()->findOrFail($this->productId)->deleteMedia((int) substr($key, 1));
        }

        unset($this->pendingImages[$key]);
        $this->galleryOrder = array_values(array_filter($this->galleryOrder, fn (string $item): bool => $item !== $key));
        unset($this->gallery);
    }


    /**
     * Save pending images and write the final gallery order.
     */
    private function storeGallery(Product $product): void
    {
        $ids = [];

        foreach ($this->galleryOrder as $key) {
            if (str_starts_with($key, 'm')) {
                $ids[] = (int) substr($key, 1);

                continue;
            }

            $file = $this->pendingImages[$key] ?? null;

            if ($file === null) {
                continue;
            }

            $ids[] = $product->addMedia($file->getRealPath())
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('gallery')
                ->id;
        }

        if ($ids !== []) {
            Media::setNewOrder($ids);
        }
    }

    /**
     * @return array<int, string>
     */
    private function savedGalleryKeys(Product $product): array
    {
        return $product->getMedia('gallery')->sortBy('order_column')->map(fn ($media) => 'm'.$media->id)->values()->all();
    }

    /**
     * Pick up files for one variant. They join that variant's gallery as pending images until the product is saved.
     */
    public function updatedVariantUploads($value, string $rowKey): void
    {
        $this->validate(["variantUploads.{$rowKey}.*" => ['image', 'max:5120']]);

        foreach ($value as $file) {
            $key = 'p'.Str::random(8);
            $this->variantPending[$rowKey][$key] = $file;
            $this->variantOrder[$rowKey][] = $key;
        }

        $this->variantUploads[$rowKey] = [];
    }

    /**
     * Images for one variant row in display order, saved and pending together.
     *
     * @return array<int, array{key: string, src: string, pending: bool}>
     */
    public function variantGallery(string $rowKey): array
    {
        $saved = collect();

        if (ctype_digit($rowKey) && $this->productId !== null) {
            $variant = ProductVariant::query()->where('product_id', $this->productId)->find((int) $rowKey);
            $saved = $variant ? $variant->getMedia('images')->keyBy(fn ($media) => 'm'.$media->id) : collect();
        }

        return collect($this->variantOrder[$rowKey] ?? [])
            ->map(function (string $key) use ($rowKey, $saved): ?array {
                if (str_starts_with($key, 'p')) {
                    $file = $this->variantPending[$rowKey][$key] ?? null;

                    return $file === null ? null : ['key' => $key, 'src' => $file->temporaryUrl(), 'pending' => true];
                }

                $media = $saved->get($key);

                return $media === null ? null : ['key' => $key, 'src' => $media->getUrl('thumb'), 'pending' => false];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Drag and drop reorder inside one variant's gallery. The composite id is "rowKey|itemKey".
     */
    public function reorderVariantImage(string $composite, int $position): void
    {
        [$rowKey, $key] = explode('|', $composite, 2);

        $order = array_values(array_filter($this->variantOrder[$rowKey] ?? [], fn (string $item): bool => $item !== $key));
        array_splice($order, $position, 0, [$key]);

        $this->variantOrder[$rowKey] = $order;
    }

    /**
     * Remove a variant image: pending ones are dropped, saved ones are deleted from the variant.
     */
    public function removeVariantImage(string $rowKey, string $key): void
    {
        if (str_starts_with($key, 'm')) {
            Gate::authorize('products.update');

            ProductVariant::query()
                ->where('product_id', $this->productId)
                ->findOrFail((int) $rowKey)
                ->deleteMedia((int) substr($key, 1));
        }

        unset($this->variantPending[$rowKey][$key]);
        $this->variantOrder[$rowKey] = array_values(array_filter($this->variantOrder[$rowKey] ?? [], fn (string $item): bool => $item !== $key));
    }

    /**
     * Save pending variant images and write each variant's gallery order. Variants are found by SKU after saving.
     */
    private function storeVariantGalleries(Product $product): void
    {
        foreach ($this->variants as $row) {
            $variant = $product->variants()->where('sku', trim($row['sku']))->first();

            if ($variant === null) {
                continue;
            }

            $ids = [];

            foreach ($this->variantOrder[$row['key']] ?? [] as $key) {
                if (str_starts_with($key, 'm')) {
                    $ids[] = (int) substr($key, 1);

                    continue;
                }

                $file = $this->variantPending[$row['key']][$key] ?? null;

                if ($file === null) {
                    continue;
                }

                $ids[] = $variant->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('images')
                    ->id;
            }

            if ($ids !== []) {
                Media::setNewOrder($ids);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function savedImageKeys(ProductVariant $variant): array
    {
        return $variant->getMedia('images')->sortBy('order_column')->map(fn ($media) => 'm'.$media->id)->values()->all();
    }

    private function blankVariant(bool $isDefault): array
    {
        return [
            'key' => 'n'.Str::random(8),
            'id' => null,
            'sku' => '',
            'name' => '',
            'price' => '',
            'compare' => '',
            'stock' => '0',
            'threshold' => 5,
            'weight' => '',
            'is_default' => $isDefault,
            'attributeValueIds' => [],
        ];
    }

    /**
     * Attach uploaded gallery and variant images after the product is saved.
     */
    private function storeUploads(Product $product): void
    {
        $this->storeGallery($product);

        $this->storeVariantGalleries($product);
    }

    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'scheduled_for' => ['required_if:status,scheduled', 'nullable', 'date'],
            'deal_ends_at' => ['nullable', 'date', 'after:now'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'hsn_code' => ['nullable', 'string', 'max:16'],
            'gst_rate' => ['required', 'integer', Rule::in([0, 5, 12, 18, 28])],
            'attributeValueIds' => ['array'],
            'attributeValueIds.*' => ['integer', 'exists:attribute_values,id'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'variants.*.name' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'variants.*.compare' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.threshold' => ['required', 'integer', 'min:0'],
            'variants.*.weight' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_default' => ['boolean'],
            'variants.*.attributeValueIds' => ['array'],
            'uploads.*' => ['image', 'max:5120'],
        ];

        foreach ($this->variants as $index => $variant) {
            $rules["variants.{$index}.sku"][] = Rule::unique('product_variants', 'sku')->ignore($variant['id']);
        }

        return $rules;
    }
}; ?>

<div class="space-y-6">
    <form wire:submit="save" class="grid gap-8 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-8">
            <flux:card class="space-y-4">
                <flux:heading>{{ __('Details') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Category') }}</flux:label>
                        <flux:select wire:model="category_id" placeholder="{{ __('Choose a category') }}">
                            @foreach ($this->categories as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="category_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Brand') }}</flux:label>
                        <flux:select wire:model="brand_id" placeholder="{{ __('No brand') }}">
                            @foreach ($this->brands as $brand)
                                <flux:select.option value="{{ $brand->id }}">{{ $brand->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="brand_id" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Short description') }}</flux:label>
                    <flux:textarea wire:model="short_description" rows="2" />
                    <flux:error name="short_description" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <div wire:ignore class="[&_.trix-button--icon-attach]:hidden [&_.trix-button-group--file-tools]:hidden">
                        <input id="product-description" type="hidden" value="{{ $description }}">
                        <trix-editor
                            input="product-description"
                            class="trix-content min-h-48 rounded-md border border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-800"
                            x-on:trix-change="$wire.set('description', $event.target.value)"
                        ></trix-editor>
                    </div>
                    <flux:description>{{ __('Use headings, lists, bold and links. Images are not supported here.') }}</flux:description>
                    <flux:error name="description" />
                </flux:field>
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Variants') }}</flux:heading>
                    <flux:button size="sm" icon="plus" wire:click="addVariant">{{ __('Add variant') }}</flux:button>
                </div>
                <flux:error name="variants" />

                @foreach ($variants as $index => $variant)
                    <div wire:key="variant-{{ $variant['key'] }}" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <flux:text variant="strong">{{ __('Variant :n', ['n' => $index + 1]) }}</flux:text>
                            <div class="flex gap-2">
                                <flux:button size="xs" wire:click="makeDefault({{ $index }})" :variant="$variant['is_default'] ? 'primary' : 'ghost'">
                                    {{ $variant['is_default'] ? __('Default') : __('Make default') }}
                                </flux:button>
                                @if (count($variants) > 1)
                                    <flux:button size="xs" variant="danger" wire:click="removeVariant({{ $index }})">{{ __('Remove') }}</flux:button>
                                @endif
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <flux:field>
                                <flux:label>{{ __('SKU') }}</flux:label>
                                <flux:input wire:model="variants.{{ $index }}.sku" />
                                <flux:error name="variants.{{ $index }}.sku" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Label') }}</flux:label>
                                <flux:input wire:model="variants.{{ $index }}.name" placeholder="{{ __('e.g. Red / M') }}" />
                                <flux:error name="variants.{{ $index }}.name" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Weight (g)') }}</flux:label>
                                <flux:input type="number" wire:model="variants.{{ $index }}.weight" />
                                <flux:error name="variants.{{ $index }}.weight" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Price (₹)') }}</flux:label>
                                <flux:input wire:model="variants.{{ $index }}.price" inputmode="decimal" />
                                <flux:error name="variants.{{ $index }}.price" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Compare-at price (₹)') }}</flux:label>
                                <flux:input wire:model="variants.{{ $index }}.compare" inputmode="decimal" />
                                <flux:error name="variants.{{ $index }}.compare" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ $variant['id'] ? __('Stock (adjust in Inventory)') : __('Opening stock') }}</flux:label>
                                <flux:input type="number" min="0" wire:model="variants.{{ $index }}.stock" :disabled="(bool) $variant['id']" />
                                <flux:error name="variants.{{ $index }}.stock" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Low-stock alert at') }}</flux:label>
                                <flux:input type="number" min="0" wire:model="variants.{{ $index }}.threshold" />
                                <flux:error name="variants.{{ $index }}.threshold" />
                            </flux:field>
                        </div>

                        @if ($this->variantAttributeOptions->isNotEmpty())
                            <div class="space-y-2">
                                @foreach ($this->variantAttributeOptions as $attributeName => $values)
                                    <flux:text>{{ $attributeName }}</flux:text>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach ($values as $value)
                                            <label class="flex items-center gap-2 text-sm">
                                                <input type="checkbox" value="{{ $value->id }}" wire:model="variants.{{ $index }}.attributeValueIds" class="rounded border-zinc-300">
                                                {{ $value->value }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="space-y-2">
                            <flux:label>{{ __('Variant images') }}</flux:label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-zinc-300 px-3 py-2 text-sm hover:border-zinc-500 dark:border-zinc-600">
                                <flux:icon name="photo" class="size-5 text-zinc-400" />
                                <span>{{ __('Add images for this variant') }}</span>
                                <input type="file" accept="image/*" multiple wire:model="variantUploads.{{ $variant['key'] }}" class="sr-only">
                            </label>
                            <flux:error name="variantUploads.{{ $variant['key'] }}.*" />

                            @php($variantImages = $this->variantGallery($variant['key']))
                            @if ($variantImages !== [])
                                <ul wire:sort="reorderVariantImage" class="grid grid-cols-4 gap-2">
                                    @foreach ($variantImages as $image)
                                        <li wire:key="vimage-{{ $variant['key'] }}-{{ $image['key'] }}" wire:sort:item="{{ $variant['key'] }}|{{ $image['key'] }}" class="relative cursor-grab overflow-hidden rounded border border-zinc-200 dark:border-zinc-700">
                                            <img src="{{ $image['src'] }}" alt="" class="aspect-square w-full object-cover" loading="lazy">
                                            @if ($loop->first)
                                                <span class="absolute start-1 top-1 rounded bg-zinc-900/80 px-1 text-[10px] text-white">{{ __('Main') }}</span>
                                            @endif
                                            @if ($image['pending'])
                                                <span class="absolute bottom-1 start-1 rounded bg-amber-500 px-1 text-[10px] font-medium text-black">{{ __('Not saved') }}</span>
                                            @endif
                                            @if ($image['pending'])
                                                <button type="button" wire:click="removeVariantImage('{{ $variant['key'] }}', '{{ $image['key'] }}')" aria-label="{{ __('Remove image') }}" class="absolute end-1 top-1 rounded-full bg-white/90 px-1.5 text-sm leading-none text-red-700 hover:bg-white">&times;</button>
                                            @else
                                                <x-confirm-button name="confirm-variant-image-{{ $variant['key'] }}-{{ $image['key'] }}" :label="'×'" trigger-variant="ghost" trigger-class="absolute end-1 top-1 rounded-full bg-white/90 px-1.5 text-sm leading-none text-red-700 hover:bg-white" :title="__('Delete image?')" action="removeVariantImage('{{ $variant['key'] }}', '{{ $image['key'] }}')" :message="__('Delete this image from the variant?')" />
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endforeach
            </flux:card>
        </div>

        <div class="space-y-8">
            <flux:card class="space-y-4">
                <flux:heading>{{ __('Publishing') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Product type') }}</flux:label>
                    <flux:select wire:model="type">
                        @foreach (ProductType::cases() as $case)
                            <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Simple products use one variant. Variable products offer options.') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model.live="status">
                        @foreach (ProductStatus::cases() as $case)
                            <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                @if ($status === ProductStatus::Scheduled->value)
                    <flux:field>
                        <flux:label>{{ __('Publish at') }}</flux:label>
                        <flux:input type="datetime-local" wire:model="scheduled_for" />
                        <flux:error name="scheduled_for" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Deal ends at (countdown)') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="deal_ends_at" />
                    <flux:error name="deal_ends_at" />
                </flux:field>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading>{{ __('Tax and SEO') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('GST rate (%)') }}</flux:label>
                    <flux:select wire:model="gst_rate">
                        @foreach ([0, 5, 12, 18, 28] as $rate)
                            <flux:select.option value="{{ $rate }}">{{ $rate }}%</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="gst_rate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('HSN code') }}</flux:label>
                    <flux:input wire:model="hsn_code" />
                    <flux:error name="hsn_code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Meta title') }}</flux:label>
                    <flux:input wire:model="meta_title" />
                    <flux:error name="meta_title" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Meta description') }}</flux:label>
                    <flux:textarea wire:model="meta_description" rows="2" />
                    <flux:error name="meta_description" />
                </flux:field>
            </flux:card>

            @if ($this->descriptiveAttributeOptions->isNotEmpty())
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('Attributes') }}</flux:heading>
                    @foreach ($this->descriptiveAttributeOptions as $attributeName => $values)
                        <flux:text>{{ $attributeName }}</flux:text>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($values as $value)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" value="{{ $value->id }}" wire:model="attributeValueIds" class="rounded border-zinc-300">
                                    {{ $value->value }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </flux:card>
            @endif

            <flux:card class="space-y-4">
                <flux:heading>{{ __('Gallery') }}</flux:heading>
                <flux:text>{{ __('Add one or more images. Drag to reorder; the first image is the main photo. Remove with the X on an image.') }}</flux:text>

                <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-zinc-300 p-6 text-center text-sm hover:border-zinc-500 dark:border-zinc-600">
                    <flux:icon name="photo" class="size-8 text-zinc-400" />
                    <span class="font-medium">{{ __('Click to choose images') }}</span>
                    <span class="text-xs text-zinc-500">{{ __('JPG, PNG or WebP, up to 5 MB each') }}</span>
                    <input type="file" accept="image/*" multiple wire:model="uploads" class="sr-only">
                </label>
                <flux:error name="uploads.*" />

                @if (count($this->gallery) > 0)
                    <ul wire:sort="reorderGallery" class="grid grid-cols-3 gap-3">
                        @foreach ($this->gallery as $image)
                            <li wire:key="image-{{ $image['key'] }}" wire:sort:item="{{ $image['key'] }}" class="relative cursor-grab overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <img src="{{ $image['src'] }}" alt="" class="aspect-square w-full object-cover" loading="lazy">
                                @if ($loop->first)
                                    <span class="absolute start-1 top-1 rounded bg-zinc-900/80 px-1.5 text-xs text-white">{{ __('Main') }}</span>
                                @endif
                                @if ($image['pending'])
                                    <span class="absolute bottom-1 start-1 rounded bg-amber-500 px-1.5 text-xs font-medium text-black">{{ __('Not saved') }}</span>
                                @endif
                                @if ($image['pending'])
                                    <button type="button" wire:click="removeImage('{{ $image['key'] }}')" aria-label="{{ __('Remove image') }}" class="absolute end-1 top-1 rounded-full bg-white/90 px-1.5 text-sm leading-none text-red-700 hover:bg-white">&times;</button>
                                @else
                                    <x-confirm-button name="confirm-product-image-{{ $image['key'] }}" :label="'×'" trigger-variant="ghost" trigger-class="absolute end-1 top-1 rounded-full bg-white/90 px-1.5 text-sm leading-none text-red-700 hover:bg-white" :title="__('Delete image?')" action="removeImage('{{ $image['key'] }}')" :message="__('Delete this image from the product?')" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>

            <div class="flex justify-end gap-2">
                <flux:button :href="route('admin.products.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save product') }}</flux:button>
            </div>
        </div>
    </form>
</div>
