<?php

use Livewire\Attributes\Layout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\MoneyFormatter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Orders')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

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

        <flux:table :paginate="$this->orders">
            <flux:table.columns>
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
                        <flux:table.cell variant="strong">
                            <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="underline-offset-2 hover:underline">{{ $order->order_number }}</a>
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
                        <flux:table.cell colspan="6"><flux:text>{{ __('No orders match these filters.') }}</flux:text></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
