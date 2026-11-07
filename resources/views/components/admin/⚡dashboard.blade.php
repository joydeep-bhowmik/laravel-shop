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
    #[Url(as: 'year')]
    public int $year = 0;

    /** Range filter start (Y-m-d). When both ends are set, the status pie counts orders in that range. */
    #[Url(as: 'from')]
    public string $from = '';

    /** Range filter end (Y-m-d). */
    #[Url(as: 'to')]
    public string $to = '';

    /** Month filter: 0 means all months. Narrows the status pie and highlights one bar in the charts. */
    #[Url(as: 'month')]
    public int $month = 0;

    public function mount(): void
    {
        $this->year = $this->year ?: (int) now()->year;
    }

    /** Statuses that count as revenue: money has been captured and the order was not voided. */
    private const REVENUE_STATUSES = ['paid', 'processing', 'shipped', 'delivered'];

    /** Slice colours for the order status pie chart. */
    private const STATUS_COLORS = [
        'pending' => '#f59e0b',
        'paid' => '#0ea5e9',
        'processing' => '#8b5cf6',
        'shipped' => '#3b82f6',
        'delivered' => '#22c55e',
        'cancelled' => '#ef4444',
        'returned' => '#f97316',
        'refunded' => '#71717a',
    ];


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
        $year = $this->year ?: (int) now()->year;
        $totals = array_fill(1, 12, 0);

        Order::query()
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereYear('placed_at', $year)
            ->get(['placed_at', 'total_paise'])
            ->each(function (Order $order) use (&$totals): void {
                $totals[(int) $order->placed_at->format('n')] += (int) $order->total_paise;
            });

        return collect(range(1, 12))
            ->map(fn (int $month): array => [
                'label' => Carbon::create($year, $month, 1)->format('M'),
                'total' => $totals[$month],
                'active' => $this->month <= 0 || $this->month === $month,
            ])
            ->all();
    }

    /**
     * Order counts per status for the chosen year and month, with each slice's share of the total.
     *
     * @return array<int, array{label: string, count: int, percent: float, color: string}>
     */
    #[Computed]
    public function statusBreakdown(): array
    {
        $year = $this->year ?: (int) now()->year;

        [$rangeStart, $rangeEnd] = $this->dateRange();

        if ($rangeStart !== null && $rangeEnd !== null) {
            $query = Order::query()->whereBetween('placed_at', [$rangeStart, $rangeEnd]);
        } else {
            $query = Order::query()->whereYear('placed_at', $year);

            if ($this->month > 0) {
                $query->whereMonth('placed_at', $this->month);
            }
        }

        $counts = $query->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $counts->sum();

        return collect(OrderStatus::cases())
            ->map(function (OrderStatus $status) use ($counts, $total): array {
                $count = (int) ($counts[$status->value] ?? 0);

                return [
                    'label' => $status->label(),
                    'count' => $count,
                    'percent' => $total > 0 ? $count / $total * 100 : 0.0,
                    'color' => self::STATUS_COLORS[$status->value],
                ];
            })
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * New customer accounts per month for the chosen year, with the running total of customers at each month end.
     *
     * @return array<int, array{label: string, new: int, total: int}>
     */
    #[Computed]
    public function userGrowth(): array
    {
        $year = $this->year ?: (int) now()->year;
        $customers = User::query()->whereDoesntHave('roles');

        $running = (clone $customers)->where('created_at', '<', Carbon::create($year, 1, 1)->startOfDay())->count();

        $newByMonth = array_fill(1, 12, 0);

        (clone $customers)
            ->whereYear('created_at', $year)
            ->get(['created_at'])
            ->each(function (User $user) use (&$newByMonth): void {
                $newByMonth[(int) $user->created_at->format('n')]++;
            });

        return collect(range(1, 12))
            ->map(function (int $month) use ($year, $newByMonth, &$running): array {
                $running += $newByMonth[$month];

                return [
                    'label' => Carbon::create($year, $month, 1)->format('M'),
                    'active' => $this->month <= 0 || $this->month === $month,
                    'new' => $newByMonth[$month],
                    'total' => $running,
                ];
            })
            ->all();
    }

    /**
     * The range filter as start and end moments, or [null, null] when it is not fully set.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function dateRange(): array
    {
        if ($this->month !== -1) {
            return [null, null];
        }

        if ($this->from === '' || $this->to === '') {
            return [null, null];
        }

        try {
            $start = Carbon::parse($this->from)->startOfDay();
            $end = Carbon::parse($this->to)->endOfDay();
        } catch (\Throwable) {
            return [null, null];
        }

        return $start->greaterThan($end) ? [$end->startOfDay(), $start->endOfDay()] : [$start, $end];
    }

    /**
     * CSS conic-gradient stops for the pie chart, one band per status.
     */
    #[Computed]
    public function statusGradient(): string
    {
        $stops = [];
        $start = 0.0;

        foreach ($this->statusBreakdown as $row) {
            $end = $start + $row['percent'];
            $stops[] = $row['color'].' '.round($start, 2).'% '.round($end, 2).'%';
            $start = $end;
        }

        return $stops === [] ? '#e4e4e7 0% 100%' : implode(', ', $stops);
    }

    /**
     * Years to choose from: the current year back to the year of the first order.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function years(): array
    {
        $first = Order::query()->min('placed_at');
        $firstYear = $first ? (int) Carbon::parse($first)->year : (int) now()->year;

        return range((int) now()->year, min($firstYear, (int) now()->year));
    }

    #[Computed]
    public function topProducts()
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->whereIn('orders.status', self::REVENUE_STATUSES)
            ->where('orders.placed_at', '>=', now()->subDays(30))
            ->selectRaw('product_variants.product_id, MAX(order_items.product_name) as product_name, SUM(order_items.quantity) as units, SUM(order_items.line_total_paise) as revenue')
            ->groupBy('product_variants.product_id')
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
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('Overview for :year', ['year' => $year]) }}</flux:heading>
            <flux:text>{{ __('Year and month apply to the charts. The top row and the lists cover the last 30 days.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:select wire:model.live="month" class="w-36" aria-label="{{ __('Month') }}">
                <flux:select.option value="0">{{ __('All months') }}</flux:select.option>
                @foreach (range(1, 12) as $choice)
                    <flux:select.option value="{{ $choice }}">{{ \Illuminate\Support\Carbon::create(null, $choice, 1)->format('F') }}</flux:select.option>
                @endforeach
                <flux:select.option value="-1">{{ __('Custom range') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="year" class="w-32" aria-label="{{ __('Year') }}">
                @foreach ($this->years as $choice)
                    <flux:select.option value="{{ $choice }}">{{ $choice }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($this->month === -1)
                <flux:input type="date" wire:model.live="from" class="w-40" aria-label="{{ __('From date') }}" />
                <flux:input type="date" wire:model.live="to" class="w-40" aria-label="{{ __('To date') }}" />
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-6">
            <flux:card class="col-span-2">
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
        <flux:card class="col-span-2 h-full space-y-4 lg:col-span-4">
            <div class="flex items-center justify-between gap-4">
                <flux:heading>{{ __('Sales by month') }}</flux:heading>
                </div>

            @php($series = $this->series)
            @php($max = max(1, collect($series)->max('total')))
            <div class="flex h-56 items-end gap-2" role="img" aria-label="{{ __('Monthly sales for :year', ['year' => $year]) }}">
                @foreach ($series as $bucket)
                    <div class="group relative flex h-full flex-1 flex-col justify-end" wire:key="month-{{ $loop->index }}">
                        <div class="rounded-t bg-indigo-500/80 transition-opacity dark:bg-indigo-400/80 {{ $bucket['active'] ? '' : 'opacity-30' }}" style="height: {{ $bucket['total'] > 0 ? max(2, round($bucket['total'] / $max * 100)) : 0 }}%"></div>
                        <span class="pointer-events-none absolute -top-8 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-zinc-900 px-2 py-1 text-xs text-white group-hover:block">
                            {{ $bucket['label'] }} {{ $year }}: {{ MoneyFormatter::format($bucket['total']) }}
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-2 text-xs text-zinc-500">
                @foreach ($series as $bucket)
                    <span class="flex-1 text-center">{{ $bucket['label'] }}</span>
                @endforeach
            </div>
        </flux:card>

        <flux:card class="col-span-2 h-full space-y-4 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <flux:heading>{{ __('Orders by status') }}</flux:heading>
                </div>

            <div class="flex flex-col items-center gap-6 sm:flex-row">
                <div class="size-44 shrink-0 rounded-full ring-1 ring-zinc-200 dark:ring-zinc-700"
                    role="img" aria-label="{{ __('Orders by status pie chart') }}"
                    style="background: conic-gradient({{ $this->statusGradient }})"></div>

                <ul class="w-full space-y-2 text-sm">
                    @forelse ($this->statusBreakdown as $row)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2">
                                <span class="inline-block size-3 rounded-full" style="background: {{ $row['color'] }}"></span>
                                {{ $row['label'] }}
                            </span>
                            <span class="tabular-nums">{{ $row['count'] }} <span class="text-zinc-500">({{ number_format($row['percent'], 1) }}%)</span></span>
                        </li>
                    @empty
                        <li class="text-zinc-500">{{ __('No orders in this period.') }}</li>
                    @endforelse
                </ul>
            </div>
        </flux:card>

        <flux:card class="col-span-2 h-full space-y-4 lg:col-span-3">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <flux:heading>{{ __('Customer growth') }}</flux:heading>
                    <flux:text>{{ __('New customer accounts per month. Staff accounts are not counted.') }}</flux:text>
                </div>
                </div>

            @php($growth = $this->userGrowth)
            @php($growthMax = max(1, collect($growth)->max('new')))
            <div class="flex h-48 items-end gap-2" role="img" aria-label="{{ __('New customers per month for :year', ['year' => $year]) }}">
                @foreach ($growth as $bucket)
                    <div class="group relative flex h-full flex-1 flex-col justify-end" wire:key="growth-{{ $loop->index }}">
                        <div class="rounded-t bg-emerald-500/80 transition-opacity dark:bg-emerald-400/80 {{ $bucket['active'] ? '' : 'opacity-30' }}" style="height: {{ $bucket['new'] > 0 ? max(2, round($bucket['new'] / $growthMax * 100)) : 0 }}%"></div>
                        <span class="pointer-events-none absolute -top-8 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-zinc-900 px-2 py-1 text-xs text-white group-hover:block">
                            {{ $bucket['label'] }}: {{ $bucket['new'] }} {{ __('new') }} · {{ $bucket['total'] }} {{ __('total') }}
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-2 text-xs text-zinc-500">
                @foreach ($growth as $bucket)
                    <span class="flex-1 text-center">{{ $bucket['label'] }}</span>
                @endforeach
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Customers at the end of :year: :total', ['year' => $year, 'total' => collect($growth)->last()['total'] ?? 0]) }}
            </p>
        </flux:card>

            <flux:card class="col-span-2 space-y-3 lg:col-span-3">
                <flux:heading>{{ __('Top products (30 days)') }}</flux:heading>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->topProducts as $row)
                        <li>
                            <a href="{{ route('admin.products.edit', ['product' => $row->product_id]) }}" wire:navigate class="flex justify-between gap-3 rounded py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                <span class="underline-offset-2 hover:underline">{{ $row->product_name }}</span>
                                <span class="text-zinc-500">{{ $row->units }} · {{ MoneyFormatter::format((int) $row->revenue) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="py-2"><flux:text>{{ __('No sales yet.') }}</flux:text></li>
                    @endforelse
                </ul>
            </flux:card>

            <flux:card class="col-span-2 space-y-3 lg:col-span-3">
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

            <flux:card class="col-span-2 space-y-3 lg:col-span-3">
                <flux:heading>{{ __('Low stock') }}</flux:heading>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->lowStockVariants as $variant)
                        <li>
                            <a href="{{ route('admin.inventory.index', ['q' => $variant->sku]) }}" wire:navigate class="flex justify-between gap-3 rounded py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                <span class="underline-offset-2 hover:underline">{{ $variant->product->name }} <span class="text-zinc-500">({{ $variant->sku }})</span></span>
                                <span class="font-medium">{{ $variant->stock_quantity }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="py-2"><flux:text>{{ __('Nothing is running low.') }}</flux:text></li>
                    @endforelse
                </ul>
            </flux:card>
    </div>
</div>
