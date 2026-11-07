<?php

use Livewire\Attributes\Layout;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\MoneyFormatter;
use App\Services\ProductCsvService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Products')] #[Layout('layouts::admin')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'category', except: '')]
    public string $categoryId = '';

    /** @var array<int, int|string> */
    public array $selected = [];

    public string $bulkAction = '';

    #[Validate('required|file|mimes:csv,txt|max:2048')]
    public $csvFile = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    #[Computed]
    public function products()
    {
        return Product::query()
            ->with(['category', 'brand', 'defaultVariant'])
            ->withSum('variants as stock_total', 'stock_quantity')
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'like', "%{$this->search}%"));
            }))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->latest('id')
            ->paginate(15);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function applyBulkAction(): void
    {
        $ids = array_map('intval', $this->selected);

        if ($ids === []) {
            Flux::toast(variant: 'warning', text: __('Select at least one product.'));

            return;
        }

        match ($this->bulkAction) {
            'publish' => $this->bulkSetStatus($ids, ProductStatus::Published),
            'draft' => $this->bulkSetStatus($ids, ProductStatus::Draft),
            'delete' => $this->bulkDelete($ids),
            default => Flux::toast(variant: 'warning', text: __('Choose an action first.')),
        };

        $this->reset('selected', 'bulkAction');
        unset($this->products);
    }

    public function deleteProduct(int $id): void
    {
        Gate::authorize('products.delete');

        Product::query()->findOrFail($id)->delete();
        unset($this->products);
        Flux::toast(variant: 'success', text: __('Product moved to trash.'));
    }

    public function importCsv(): void
    {
        Gate::authorize('products.import');

        $this->validate();

        $result = app(ProductCsvService::class)->import($this->csvFile->getRealPath(), Auth::user());

        $this->importErrors = $result['errors'];
        $this->reset('csvFile');
        unset($this->products);

        Flux::toast(
            variant: $result['errors'] === [] ? 'success' : 'warning',
            text: __(':count variant(s) updated.', ['count' => $result['updated']]),
        );
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function bulkSetStatus(array $ids, ProductStatus $status): void
    {
        Gate::authorize('products.publish');

        $products = Product::query()->whereIn('id', $ids)->get();

        foreach ($products as $product) {
            $product->update([
                'status' => $status,
                'published_at' => $status === ProductStatus::Published ? ($product->published_at ?? now()) : null,
            ]);
        }

        Flux::toast(variant: 'success', text: __(':count product(s) set to :status.', ['count' => $products->count(), 'status' => $status->label()]));
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function bulkDelete(array $ids): void
    {
        Gate::authorize('products.delete');

        Product::query()->whereIn('id', $ids)->delete();
        Flux::toast(variant: 'success', text: __('Selected products moved to trash.'));
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-wrap items-end gap-3">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name or SKU')" class="w-64" />

                <flux:select wire:model.live="status" class="w-44">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach (ProductStatus::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="categoryId" class="w-48">
                    <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="ghost" icon="arrow-down-tray" :href="route('admin.products.export')">{{ __('Export CSV') }}</flux:button>
                @can('products.create')
                    <flux:button variant="primary" icon="plus" :href="route('admin.products.create')" wire:navigate>{{ __('New product') }}</flux:button>
                @endcan
            </div>
        </div>

        @can('products.publish')
            <div class="flex flex-wrap items-end gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:select wire:model="bulkAction" class="w-56" :placeholder="__('Bulk action')">
                    <flux:select.option value="publish">{{ __('Publish') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Move to draft') }}</flux:select.option>
                    @can('products.delete')
                        <flux:select.option value="delete">{{ __('Move to trash') }}</flux:select.option>
                    @endcan
                </flux:select>
                <flux:button wire:click="applyBulkAction" wire:confirm="{{ __('Apply this action to the selected products?') }}">{{ __('Apply') }}</flux:button>
                <flux:text>{{ trans_choice(':count selected|:count selected', count($selected), ['count' => count($selected)]) }}</flux:text>
            </div>
        @endcan

        <flux:table :paginate="$this->products">
            <flux:table.columns>
                <flux:table.column></flux:table.column>
                <flux:table.column>{{ __('Product') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Price') }}</flux:table.column>
                <flux:table.column>{{ __('Stock') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->products as $product)
                    <flux:table.row wire:key="product-{{ $product->id }}">
                        <flux:table.cell>
                            <input type="checkbox" value="{{ $product->id }}" wire:model="selected" class="rounded border-zinc-300" aria-label="{{ __('Select product') }}">
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $product->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $product->defaultVariant?->sku ?? '-' }} · {{ $product->type->label() }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $product->category->name }}</flux:table.cell>
                        <flux:table.cell>{{ $product->defaultVariant ? MoneyFormatter::format($product->defaultVariant->price_paise) : '-' }}</flux:table.cell>
                        <flux:table.cell>{{ (int) $product->stock_total }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($product->status) { ProductStatus::Published => 'green', ProductStatus::Scheduled => 'amber', default => 'zinc' }">
                                {{ $product->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                @can('products.update')
                                    <flux:button size="xs" :href="route('admin.products.edit', $product)" wire:navigate>{{ __('Edit') }}</flux:button>
                                @endcan
                                @can('products.delete')
                                    <flux:button size="xs" variant="danger" wire:click="deleteProduct({{ $product->id }})" wire:confirm="{{ __('Move this product to trash?') }}">{{ __('Delete') }}</flux:button>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <flux:text>{{ __('No products match these filters.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @can('products.import')
            <details class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <summary class="cursor-pointer font-medium">{{ __('Import prices and stock from CSV') }}</summary>
                <form wire:submit="importCsv" class="mt-4 flex flex-wrap items-end gap-3">
                    <flux:field class="w-80">
                        <flux:label>{{ __('CSV file') }}</flux:label>
                        <flux:input type="file" wire:model="csvFile" accept=".csv,text/csv" />
                        <flux:description>{{ __('Columns: sku, price (rupees, optional), stock (optional). Export first to get the format.') }}</flux:description>
                        <flux:error name="csvFile" />
                    </flux:field>
                    <flux:button type="submit" variant="primary">{{ __('Import') }}</flux:button>
                </form>

                @if ($importErrors !== [])
                    <flux:callout variant="warning" class="mt-4">
                        <ul class="list-disc ps-5">
                            @foreach ($importErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </flux:callout>
                @endif
            </details>
        @endcan
    </div>
</div>
