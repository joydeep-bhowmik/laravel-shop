<?php


use App\Enums\StockMovementReason;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $lowOnly = false;

    public bool $showAdjust = false;

    public ?int $variantId = null;

    public int $delta = 0;

    public string $reason = 'adjustment';

    public string $note = '';

    public ?int $historyVariantId = null;

    #[Computed]
    public function variants()
    {
        return ProductVariant::query()
            ->with('product')
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('sku', 'like', "%{$this->search}%")
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->lowOnly, fn ($query) => $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold'))
            ->orderBy('stock_quantity')
            ->paginate(20);
    }

    #[Computed]
    public function variant(): ?ProductVariant
    {
        return $this->variantId === null ? null : ProductVariant::query()->with('product')->find($this->variantId);
    }

    #[Computed]
    public function history()
    {
        return $this->historyVariantId === null
            ? collect()
            : StockMovement::query()
                ->with('user')
                ->where('product_variant_id', $this->historyVariantId)
                ->latest('id')
                ->limit(25)
                ->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLowOnly(): void
    {
        $this->resetPage();
    }

    public function openAdjust(int $id): void
    {
        Gate::authorize('inventory.manage');

        $this->variantId = $id;
        $this->reset('delta', 'note');
        $this->reason = 'adjustment';
        $this->showAdjust = true;
    }

    public function showHistory(int $id): void
    {
        $this->historyVariantId = $id;
        unset($this->history);
    }

    public function saveAdjustment(InventoryService $inventory): void
    {
        Gate::authorize('inventory.manage');

        $validated = $this->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', 'in:restock,adjustment'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inventory->adjust(
                $this->variant,
                (int) $validated['delta'],
                StockMovementReason::from($validated['reason']),
                $validated['note'] ?: null,
                Auth::user(),
            );
        } catch (\InvalidArgumentException $exception) {
            $this->addError('delta', $exception->getMessage());

            return;
        }

        $this->showAdjust = false;
        $this->historyVariantId = $this->variantId;
        unset($this->variants, $this->history);
        Flux::toast(variant: 'success', text: __('Stock updated.'));
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name or SKU')" class="w-64" />
            <flux:switch wire:model.live="lowOnly" :label="__('Low stock only')" />
        </div>

        <flux:table :paginate="$this->variants">
            <flux:table.columns>
                <flux:table.column>{{ __('Product') }}</flux:table.column>
                <flux:table.column>{{ __('SKU') }}</flux:table.column>
                <flux:table.column>{{ __('In stock') }}</flux:table.column>
                <flux:table.column>{{ __('Alert at') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->variants as $variant)
                    <flux:table.row wire:key="variant-{{ $variant->id }}">
                        <flux:table.cell variant="strong">
                            {{ $variant->product->name }}
                            @if ($variant->name)<span class="text-zinc-500">· {{ $variant->name }}</span>@endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $variant->sku }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$variant->stock_quantity === 0 ? 'red' : ($variant->isLowStock() ? 'amber' : 'green')">
                                {{ $variant->stock_quantity }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $variant->low_stock_threshold }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" wire:click="showHistory({{ $variant->id }})">{{ __('History') }}</flux:button>
                                @can('inventory.manage')
                                    <flux:button size="xs" wire:click="openAdjust({{ $variant->id }})">{{ __('Adjust') }}</flux:button>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($historyVariantId)
            <flux:card class="space-y-3">
                <flux:heading>{{ __('Stock history') }}</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('When') }}</flux:table.column>
                        <flux:table.column>{{ __('Change') }}</flux:table.column>
                        <flux:table.column>{{ __('After') }}</flux:table.column>
                        <flux:table.column>{{ __('Reason') }}</flux:table.column>
                        <flux:table.column>{{ __('By') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->history as $movement)
                            <flux:table.row wire:key="movement-{{ $movement->id }}">
                                <flux:table.cell>{{ $movement->created_at->format('d M Y, H:i') }}</flux:table.cell>
                                <flux:table.cell>{{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}</flux:table.cell>
                                <flux:table.cell>{{ $movement->quantity_after }}</flux:table.cell>
                                <flux:table.cell>{{ $movement->reason->label() }}{{ $movement->note ? ' · '.$movement->note : '' }}</flux:table.cell>
                                <flux:table.cell>{{ $movement->user?->name ?? __('System') }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5"><flux:text>{{ __('No movements recorded.') }}</flux:text></flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    </div>

    <flux:modal wire:model="showAdjust" class="md:w-[28rem]">
        <form wire:submit="saveAdjustment" class="space-y-4">
            <flux:heading size="lg">{{ __('Adjust stock') }}</flux:heading>
            @if ($this->variant)
                <flux:text>{{ $this->variant->product->name }} · {{ $this->variant->sku }} · {{ __('Currently :n', ['n' => $this->variant->stock_quantity]) }}</flux:text>
            @endif

            <flux:field>
                <flux:label>{{ __('Change (use a minus sign to remove)') }}</flux:label>
                <flux:input type="number" wire:model="delta" />
                <flux:error name="delta" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Reason') }}</flux:label>
                <flux:select wire:model="reason">
                    @foreach ([StockMovementReason::Restock, StockMovementReason::Adjustment] as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Note') }}</flux:label>
                <flux:input wire:model="note" />
                <flux:error name="note" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showAdjust', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
