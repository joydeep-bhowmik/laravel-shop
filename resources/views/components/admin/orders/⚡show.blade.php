<?php


use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\MoneyFormatter;
use App\Services\OrderStatusService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Order $record;

    public string $trackingNumber = '';

    public string $courier = '';

    public string $internalNotes = '';

    public string $transitionNote = '';

    public function mount(int $order): void
    {
        Gate::authorize('orders.view');

        $this->record = Order::query()->findOrFail($order)->load(['user', 'items', 'payments', 'statusHistories.user', 'refunds']);
        $this->trackingNumber = (string) $this->record->tracking_number;
        $this->courier = (string) $this->record->courier;
        $this->internalNotes = (string) $this->record->internal_notes;
    }

    #[Computed]
    public function nextStatuses(): array
    {
        return $this->record->status->allowedTransitions();
    }

    public function moveTo(string $status, OrderStatusService $service): void
    {
        $next = OrderStatus::from($status);

        Gate::authorize($next === OrderStatus::Cancelled ? 'orders.cancel' : 'orders.update');

        try {
            $this->record = $service->transition($this->record, $next, Auth::user(), $this->transitionNote ?: null);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->reset('transitionNote');
        $this->refreshOrder();
        Flux::toast(variant: 'success', text: __('Order is now :status.', ['status' => $next->label()]));
    }

    public function saveFulfilment(): void
    {
        Gate::authorize('orders.update');

        $validated = $this->validate([
            'trackingNumber' => ['nullable', 'string', 'max:100'],
            'courier' => ['nullable', 'string', 'max:100'],
        ]);

        $this->record->update([
            'tracking_number' => $validated['trackingNumber'] ?: null,
            'courier' => $validated['courier'] ?: null,
        ]);

        $this->refreshOrder();
        Flux::toast(variant: 'success', text: __('Shipping details saved.'));
    }

    public function saveNotes(): void
    {
        Gate::authorize('orders.update');

        $validated = $this->validate(['internalNotes' => ['nullable', 'string', 'max:5000']]);

        $this->record->update(['internal_notes' => $validated['internalNotes'] ?: null]);

        $this->refreshOrder();
        Flux::toast(variant: 'success', text: __('Notes saved.'));
    }

    private function refreshOrder(): void
    {
        $this->record = $this->record->fresh(['user', 'items', 'payments', 'statusHistories.user', 'refunds']);
        unset($this->nextStatuses);
    }
}; ?>

<div class="space-y-6">order_number">
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <flux:heading>{{ $record->order_number }}</flux:heading>
                        <flux:text>{{ __('Placed :when by :name', ['when' => $record->placed_at?->format('d M Y, H:i'), 'name' => $record->user->name]) }}</flux:text>
                    </div>
                    <flux:badge size="lg">{{ $record->status->label() }}</flux:badge>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Product') }}</flux:table.column>
                        <flux:table.column>{{ __('Qty') }}</flux:table.column>
                        <flux:table.column>{{ __('Unit') }}</flux:table.column>
                        <flux:table.column>{{ __('Line total') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($record->items as $item)
                            <flux:table.row wire:key="item-{{ $item->id }}">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $item->product_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $item->sku }}{{ $item->variant_name ? ' · '.$item->variant_name : '' }}</div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $item->quantity }}</flux:table.cell>
                                <flux:table.cell>{{ MoneyFormatter::format($item->unit_price_paise) }}</flux:table.cell>
                                <flux:table.cell>{{ MoneyFormatter::format($item->line_total_paise) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                <dl class="ms-auto grid w-full max-w-sm grid-cols-2 gap-x-6 gap-y-1 text-sm">
                    <dt>{{ __('Subtotal') }}</dt><dd class="text-end">{{ MoneyFormatter::format($record->subtotal_paise) }}</dd>
                    <dt>{{ __('Discount') }}</dt><dd class="text-end">-{{ MoneyFormatter::format($record->discount_paise) }}</dd>
                    <dt>{{ __('Shipping') }}</dt><dd class="text-end">{{ MoneyFormatter::format($record->shipping_paise) }}</dd>
                    <dt>{{ __('CGST') }}</dt><dd class="text-end">{{ MoneyFormatter::format($record->cgst_paise) }}</dd>
                    <dt>{{ __('SGST') }}</dt><dd class="text-end">{{ MoneyFormatter::format($record->sgst_paise) }}</dd>
                    <dt>{{ __('IGST') }}</dt><dd class="text-end">{{ MoneyFormatter::format($record->igst_paise) }}</dd>
                    <dt class="font-semibold">{{ __('Total') }}</dt><dd class="text-end font-semibold">{{ MoneyFormatter::format($record->total_paise) }}</dd>
                </dl>
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Timeline') }}</flux:heading>
                <ol class="space-y-2 text-sm">
                    @foreach ($record->statusHistories as $entry)
                        <li wire:key="history-{{ $entry->id }}" class="flex flex-wrap gap-2">
                            <span class="text-zinc-500">{{ $entry->created_at->format('d M Y, H:i') }}</span>
                            <span class="font-medium">{{ $entry->to_status->label() }}</span>
                            <span class="text-zinc-500">{{ $entry->user?->name ?? __('System') }}</span>
                            @if ($entry->note)<span class="text-zinc-500">· {{ $entry->note }}</span>@endif
                        </li>
                    @endforeach
                </ol>
            </flux:card>
        </div>

        <div class="space-y-6">
            @can('orders.update')
                <flux:card class="space-y-4">
                    <flux:heading>{{ __('Status') }}</flux:heading>
                    <flux:field>
                        <flux:label>{{ __('Note for this change') }}</flux:label>
                        <flux:input wire:model="transitionNote" />
                    </flux:field>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($this->nextStatuses as $next)
                            @if ($next !== OrderStatus::Cancelled || Gate::allows('orders.cancel'))
                                <flux:button size="sm" :variant="$next === OrderStatus::Cancelled ? 'danger' : 'primary'"
                                    wire:click="moveTo('{{ $next->value }}')"
                                    wire:confirm="{{ __('Move this order to :status?', ['status' => $next->label()]) }}">
                                    {{ $next->label() }}
                                </flux:button>
                            @endif
                        @empty
                            <flux:text>{{ __('No further status changes are possible.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:heading>{{ __('Shipping') }}</flux:heading>
                    <form wire:submit="saveFulfilment" class="space-y-3">
                        <flux:field>
                            <flux:label>{{ __('Tracking number') }}</flux:label>
                            <flux:input wire:model="trackingNumber" />
                            <flux:error name="trackingNumber" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Courier') }}</flux:label>
                            <flux:input wire:model="courier" />
                            <flux:error name="courier" />
                        </flux:field>
                        <flux:button type="submit" size="sm">{{ __('Save shipping') }}</flux:button>
                    </form>
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:heading>{{ __('Internal notes') }}</flux:heading>
                    <form wire:submit="saveNotes" class="space-y-3">
                        <flux:textarea wire:model="internalNotes" rows="4" />
                        <flux:error name="internalNotes" />
                        <flux:button type="submit" size="sm">{{ __('Save notes') }}</flux:button>
                    </form>
                </flux:card>
            @endcan

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Deliver to') }}</flux:heading>
                <address class="text-sm not-italic leading-6">
                    {{ $record->shipping_address['full_name'] }}<br>
                    {{ $record->shipping_address['line1'] }}<br>
                    @if (! empty($record->shipping_address['line2'])){{ $record->shipping_address['line2'] }}<br>@endif
                    {{ $record->shipping_address['city'] }}, {{ $record->shipping_address['state'] }} {{ $record->shipping_address['pincode'] }}<br>
                    {{ $record->shipping_address['phone'] }}
                </address>
                <flux:text>{{ __('Payment: :status', ['status' => $record->payment_status->label()]) }}</flux:text>
            </flux:card>
        </div>
    </div>
</div>
