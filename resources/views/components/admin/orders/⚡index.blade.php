<?php


use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderStatusService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Services\MoneyFormatter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /** @var array<int, string> */
    public array $selected = [];

    public string $bulkStatus = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->with('user')
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('order_number', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$this->search}%"));
            }))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest('placed_at')
            ->paginate(20);
    }

    /**
     * Select every order on this page, or clear the selection when they are all selected.
     */
    public function toggleAll(): void
    {
        $pageIds = $this->orders->getCollection()->pluck('id')->map(fn ($id): string => (string) $id)->all();

        $this->selected = $this->allOnPageSelected ? [] : $pageIds;
    }

    #[Computed]
    public function allOnPageSelected(): bool
    {
        $pageIds = $this->orders->getCollection()->pluck('id')->map(fn ($id): string => (string) $id)->all();

        return $pageIds !== [] && count(array_intersect($pageIds, array_map('strval', $this->selected))) === count($pageIds);
    }

    /**
     * Move every selected order to the chosen status. Orders that cannot move there are skipped and counted.
     */
    public function applyBulkStatus(OrderStatusService $service): void
    {
        $target = OrderStatus::tryFrom($this->bulkStatus);

        if ($target === null || $this->selected === []) {
            Flux::toast(variant: 'warning', text: __('Choose a status and at least one order.'));

            return;
        }

        Gate::authorize($target === OrderStatus::Cancelled ? 'orders.cancel' : 'orders.update');

        $updated = 0;
        $skipped = 0;

        foreach (Order::query()->whereIn('id', array_map('intval', $this->selected))->get() as $order) {
            if (! $order->status->canTransitionTo($target)) {
                $skipped++;

                continue;
            }

            $service->transition($order, $target, Auth::user(), __('Bulk update'));
            $updated++;
        }

        $this->reset('selected', 'bulkStatus');
        unset($this->orders);

        Flux::toast(variant: 'success', text: __(':updated order(s) moved to :status. :skipped skipped.', [
            'updated' => $updated,
            'status' => $target->label(),
            'skipped' => $skipped,
        ]));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Order number or customer email')" class="w-72" />
            <flux:select wire:model.live="status" class="w-44">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (OrderStatus::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($selected !== [])
            <div class="flex flex-wrap items-end gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:select wire:model="bulkStatus" class="w-56" placeholder="{{ __('Set status to') }}">
                    @foreach (OrderStatus::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <x-confirm-button name="confirm-bulk-orders" variant="primary" :label="__('Apply')" action="applyBulkStatus" :message="__('Move the selected orders to this status? Orders that cannot move there are skipped.')" />
                <flux:text>{{ trans_choice(':count selected|:count selected', count($selected), ['count' => count($selected)]) }}</flux:text>
            </div>
        @endif

        <flux:table :paginate="$this->orders">
            <flux:table.columns>
                <flux:table.column>
                    <input type="checkbox" wire:click="toggleAll" @checked($this->allOnPageSelected) class="rounded border-zinc-300" aria-label="{{ __('Select all on this page') }}">
                </flux:table.column>
                <flux:table.column>{{ __('Order') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Placed') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Payment') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->orders as $order)
                    <flux:table.row wire:key="order-{{ $order->id }}">
                        <flux:table.cell>
                            <input type="checkbox" value="{{ $order->id }}" wire:model="selected" class="rounded border-zinc-300" aria-label="{{ __('Select order') }}">
                        </flux:table.cell>
                        <flux:table.cell variant="strong">
                            <a href="{{ route('admin.orders.show', ['order' => $order]) }}" wire:navigate class="underline-offset-2 hover:underline">{{ $order->order_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $order->user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $order->placed_at?->format('d M Y, H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ MoneyFormatter::format($order->total_paise) }}</flux:table.cell>
                        <flux:table.cell>{{ $order->payment_status->label() }} · {{ $order->payment_method->label() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $order->status->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7"><flux:text>{{ __('No orders match these filters.') }}</flux:text></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
