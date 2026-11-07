<?php


use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MoneyFormatter;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    #[Url(as: 'period', except: 'daily')]
    public string $period = 'daily';

    /** Statuses that count as revenue: money has been captured and the order was not voided. */
    private const REVENUE_STATUSES = ['paid', 'processing', 'shipped', 'delivered'];

    #[Computed]
    public function summary(): array
    {
        $since = now()->subDays(30);

        $orders = Order::query()
            ->whereIn('status', self::REVENUE_STATUSES)
            ->where('placed_at', '>=', $since);

        $revenue = (int) (clone $orders)->sum('total_paise');
        $count = (clone $orders)->count();

        return [
            'revenue' => $revenue,
            'orders' => $count,
            'averageOrder' => $count > 0 ? intdiv($revenue, $count) : 0,
            'newCustomers' => User::query()->where('created_at', '>=', $since)->whereDoesntHave('roles')->count(),
            'lowStock' => ProductVariant::query()->where('is_active', true)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
        ];
    }

    /**
     * Bucketed revenue for the sales chart.
     *
     * @return array<int, array{label: string, total: int}>
     */
    #[Computed]
    public function series(): array
    {
        [$buckets, $format, $step] = match ($this->period) {
            'weekly' => [12, 'd M', 'weeks'],
            'monthly' => [12, 'M Y', 'months'],
            default => [30, 'd M', 'days'],
        };

        $rows = [];

        for ($i = $buckets - 1; $i >= 0; $i--) {
            $moment = now()->{"sub".ucfirst($step)}($i);
            $start = $step === 'weeks' ? $moment->copy()->startOfWeek() : ($step === 'months' ? $moment->copy()->startOfMonth() : $moment->copy()->startOfDay());
            $end = $step === 'weeks' ? $start->copy()->addWeek() : ($step === 'months' ? $start->copy()->addMonth() : $start->copy()->addDay());

            $rows[] = [
                'label' => $start->format($format),
                'total' => (int) Order::query()
                    ->whereIn('status', self::REVENUE_STATUSES)
                    ->where('placed_at', '>=', $start)
                    ->where('placed_at', '<', $end)
                    ->sum('total_paise'),
            ];
        }

        return $rows;
    }

    #[Computed]
    public function topProducts()
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::REVENUE_STATUSES)
            ->where('orders.placed_at', '>=', now()->subDays(30))
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as units, SUM(order_items.line_total_paise) as revenue')
            ->groupBy('order_items.product_name')
            ->orderByDesc('units')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentOrders()
    {
        return Order::query()->with('user')->latest('placed_at')->limit(5)->get();
    }

    #[Computed]
    public function lowStockVariants()
    {
        return ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit(8)
            ->get();
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-8">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <flux:card>
                <flux:text>{{ __('Revenue (30 days)') }}</flux:text>
                <div class="text-2xl font-semibold">{{ MoneyFormatter::format($this->summary['revenue']) }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Orders (30 days)') }}</flux:text>
                <div class="text-2xl font-semibold">{{ $this->summary['orders'] }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Average order value') }}</flux:text>
                <div class="text-2xl font-semibold">{{ MoneyFormatter::format($this->summary['averageOrder']) }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('New customers (30 days)') }}</flux:text>
                <div class="text-2xl font-semibold">{{ $this->summary['newCustomers'] }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Low-stock variants') }}</flux:text>
                <div class="text-2xl font-semibold {{ $this->summary['lowStock'] > 0 ? 'text-amber-600' : '' }}">{{ $this->summary['lowStock'] }}</div>
            </flux:card>
        </div>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Sales') }}</flux:heading>
                <flux:select wire:model.live="period" class="w-40">
                    <flux:select.option value="daily">{{ __('Last 30 days') }}</flux:select.option>
                    <flux:select.option value="weekly">{{ __('Last 12 weeks') }}</flux:select.option>
                    <flux:select.option value="monthly">{{ __('Last 12 months') }}</flux:select.option>
                </flux:select>
            </div>

            @php($max = max(1, collect($this->series)->max('total')))
            <div class="flex h-48 items-end gap-1" role="img" aria-label="{{ __('Sales chart') }}">
                @foreach ($this->series as $bucket)
                    <div class="group relative flex h-full flex-1 flex-col justify-end" wire:key="bucket-{{ $loop->index }}">
                        <div class="rounded-t bg-indigo-500/80 dark:bg-indigo-400/80" style="height: {{ max(2, round($bucket['total'] / $max * 100)) }}%"></div>
                        <span class="pointer-events-none absolute -top-8 left-1/2 hidden -translate-x-1/2 rounded bg-zinc-900 px-2 py-1 text-xs text-white group-hover:block">
                            {{ $bucket['label'] }}: {{ MoneyFormatter::format($bucket['total']) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <div class="grid gap-6 lg:grid-cols-3">
            <flux:card class="space-y-3">
                <flux:heading>{{ __('Top products (30 days)') }}</flux:heading>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->topProducts as $row)
                        <li class="flex justify-between py-2 text-sm">
                            <span>{{ $row->product_name }}</span>
                            <span class="text-zinc-500">{{ $row->units }} · {{ MoneyFormatter::format((int) $row->revenue) }}</span>
                        </li>
                    @empty
                        <li class="py-2"><flux:text>{{ __('No sales yet.') }}</flux:text></li>
                    @endforelse
                </ul>
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Recent orders') }}</flux:heading>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->recentOrders as $order)
                        <li class="flex justify-between py-2 text-sm">
                            <a href="{{ route('admin.orders.show', ['order' => $order]) }}" wire:navigate class="font-medium underline-offset-2 hover:underline">{{ $order->order_number }}</a>
                            <span class="text-zinc-500">{{ $order->status->label() }}</span>
                        </li>
                    @empty
                        <li class="py-2"><flux:text>{{ __('No orders yet.') }}</flux:text></li>
                    @endforelse
                </ul>
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Low stock') }}</flux:heading>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->lowStockVariants as $variant)
                        <li class="flex justify-between py-2 text-sm">
                            <span>{{ $variant->product->name }} <span class="text-zinc-500">({{ $variant->sku }})</span></span>
                            <span class="font-medium">{{ $variant->stock_quantity }}</span>
                        </li>
                    @empty
                        <li class="py-2"><flux:text>{{ __('Nothing is running low.') }}</flux:text></li>
                    @endforelse
                </ul>
            </flux:card>
        </div>
    </div>
</div>
