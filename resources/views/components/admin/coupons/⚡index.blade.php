<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Services\MoneyFormatter;
use App\Services\SortsAndFiltersTables;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use SortsAndFiltersTables, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $description = '';

    public string $type = 'percentage';

    public string $value = '';

    public string $maxDiscount = '';

    public string $minOrder = '';

    public string $usageLimit = '';

    public string $perUserLimit = '';

    public string $startsAt = '';

    public string $expiresAt = '';

    public bool $isActive = true;

    #[Computed]
    public function coupons()
    {
        return Coupon::query()
            ->when($this->search !== '', fn ($query) => $query->where('code', 'like', '%'.Str::upper($this->search).'%'))
            ->tap(fn ($query) => $this->applyDateRange($query, 'created_at'))
            ->tap(fn ($query) => $this->applyTableSort($query, [
                'code' => 'code',
                'value' => 'value',
                'used' => 'usage_count',
                'expires' => 'expires_at',
                'status' => 'is_active',
                'created_at' => 'created_at',
            ]))
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('coupons.manage');

        $this->reset('editingId', 'code', 'description', 'value', 'maxDiscount', 'minOrder', 'usageLimit', 'perUserLimit', 'startsAt', 'expiresAt');
        $this->type = 'percentage';
        $this->isActive = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('coupons.manage');

        $coupon = Coupon::query()->findOrFail($id);

        $this->editingId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = (string) $coupon->description;
        $this->type = $coupon->type->value;
        $this->value = $coupon->type === CouponType::Fixed
            ? number_format($coupon->value / 100, 2, '.', '')
            : (string) $coupon->value;
        $this->maxDiscount = $coupon->max_discount_paise !== null ? number_format($coupon->max_discount_paise / 100, 2, '.', '') : '';
        $this->minOrder = $coupon->min_order_paise > 0 ? number_format($coupon->min_order_paise / 100, 2, '.', '') : '';
        $this->usageLimit = (string) $coupon->usage_limit;
        $this->perUserLimit = (string) $coupon->per_user_limit;
        $this->startsAt = $coupon->starts_at?->format('Y-m-d') ?? '';
        $this->expiresAt = $coupon->expires_at?->format('Y-m-d') ?? '';
        $this->isActive = $coupon->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('coupons.manage');

        $this->code = Str::upper(trim($this->code));

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0.01'],
            'maxDiscount' => ['nullable', 'numeric', 'min:0.01'],
            'minOrder' => ['nullable', 'numeric', 'min:0'],
            'usageLimit' => ['nullable', 'integer', 'min:1'],
            'perUserLimit' => ['nullable', 'integer', 'min:1'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
        ]);

        if ($validated['type'] === CouponType::Percentage->value && (float) $validated['value'] > 100) {
            $this->addError('value', __('A percentage cannot be more than 100.'));

            return;
        }

        $coupon = $this->editingId !== null ? Coupon::query()->findOrFail($this->editingId) : new Coupon;

        $coupon->fill([
            'code' => $this->code,
            'description' => $this->description ?: null,
            'type' => $validated['type'],
            'value' => $validated['type'] === CouponType::Fixed->value
                ? MoneyFormatter::toPaise($this->value)
                : (int) round((float) $this->value),
            'max_discount_paise' => $validated['type'] === CouponType::Percentage->value && $this->maxDiscount !== ''
                ? MoneyFormatter::toPaise($this->maxDiscount)
                : null,
            'min_order_paise' => $this->minOrder !== '' ? MoneyFormatter::toPaise($this->minOrder) : 0,
            'usage_limit' => $this->usageLimit !== '' ? (int) $this->usageLimit : null,
            'per_user_limit' => $this->perUserLimit !== '' ? (int) $this->perUserLimit : null,
            'starts_at' => $this->startsAt !== '' ? $this->startsAt : null,
            'expires_at' => $this->expiresAt !== '' ? $this->expiresAt.' 23:59:59' : null,
            'is_active' => $this->isActive,
        ]);
        $coupon->save();

        $this->showForm = false;
        $this->reset('editingId');
        Flux::toast(variant: 'success', text: __('Coupon saved.'));
    }

    public function delete(int $id): void
    {
        Gate::authorize('coupons.manage');

        $coupon = Coupon::query()->findOrFail($id);

        if ($coupon->usages()->exists()) {
            Flux::toast(variant: 'danger', text: __('This coupon has been used. Deactivate it instead of deleting it.'));

            return;
        }

        $coupon->delete();
        Flux::toast(variant: 'success', text: __('Coupon deleted.'));
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search code')" class="w-64" />
        <x-admin.date-range />
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New coupon') }}</flux:button>
    </div>

    <flux:table :paginate="$this->coupons">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sort === 'code'" :direction="$direction" wire:click="sortBy('code')">{{ __('Code') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'value'" :direction="$direction" wire:click="sortBy('value')">{{ __('Discount') }}</flux:table.column>
            <flux:table.column>{{ __('Min order') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'used'" :direction="$direction" wire:click="sortBy('used')">{{ __('Used') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'expires'" :direction="$direction" wire:click="sortBy('expires')">{{ __('Expires') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'status'" :direction="$direction" wire:click="sortBy('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->coupons as $coupon)
                <flux:table.row wire:key="coupon-{{ $coupon->id }}">
                    <flux:table.cell variant="strong">
                        {{ $coupon->code }}
                        @if ($coupon->description)<div class="text-xs font-normal text-zinc-500">{{ $coupon->description }}</div>@endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $coupon->type === CouponType::Percentage ? $coupon->value.'%' : MoneyFormatter::format($coupon->value).' '.__('off') }}
                        @if ($coupon->max_discount_paise)<div class="text-xs text-zinc-500">{{ __('up to :amount', ['amount' => MoneyFormatter::format($coupon->max_discount_paise)]) }}</div>@endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $coupon->min_order_paise > 0 ? MoneyFormatter::format($coupon->min_order_paise) : '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $coupon->usage_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</flux:table.cell>
                    <flux:table.cell>{{ $coupon->expires_at?->format('d M Y') ?? __('Never') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$coupon->is_active ? 'green' : 'zinc'">{{ $coupon->is_active ? __('Active') : __('Off') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="xs" wire:click="edit({{ $coupon->id }})">{{ __('Edit') }}</flux:button>
                            <x-confirm-button name="confirm-coupon-{{ $coupon->id }}" size="xs" :label="__('Delete')" action="delete({{ $coupon->id }})" :message="__('Delete this coupon? Coupons that have been used cannot be deleted.')" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7"><flux:text>{{ __('No coupons yet.') }}</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showForm" class="md:w-[36rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit coupon') : __('New coupon') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Code') }}</flux:label>
                    <flux:input wire:model="code" class="uppercase" placeholder="WELCOME10" />
                    <flux:error name="code" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <flux:select wire:model.live="type">
                        <flux:select.option value="percentage">{{ __('Percentage off') }}</flux:select.option>
                        <flux:select.option value="fixed">{{ __('Fixed amount off (₹)') }}</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:input wire:model="description" />
                <flux:error name="description" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ $type === 'percentage' ? __('Percent off') : __('Amount off (₹)') }}</flux:label>
                    <flux:input wire:model="value" inputmode="decimal" />
                    <flux:error name="value" />
                </flux:field>
                @if ($type === 'percentage')
                    <flux:field>
                        <flux:label>{{ __('Maximum discount (₹, optional)') }}</flux:label>
                        <flux:input wire:model="maxDiscount" inputmode="decimal" />
                        <flux:error name="maxDiscount" />
                    </flux:field>
                @endif
                <flux:field>
                    <flux:label>{{ __('Minimum order (₹)') }}</flux:label>
                    <flux:input wire:model="minOrder" inputmode="decimal" />
                    <flux:error name="minOrder" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Total uses allowed') }}</flux:label>
                    <flux:input type="number" min="1" wire:model="usageLimit" />
                    <flux:description>{{ __('Leave empty for unlimited.') }}</flux:description>
                    <flux:error name="usageLimit" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Uses per customer') }}</flux:label>
                    <flux:input type="number" min="1" wire:model="perUserLimit" />
                    <flux:error name="perUserLimit" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Valid from') }}</flux:label>
                    <flux:input type="date" wire:model="startsAt" />
                    <flux:error name="startsAt" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Expires on') }}</flux:label>
                    <flux:input type="date" wire:model="expiresAt" />
                    <flux:error name="expiresAt" />
                </flux:field>
            </div>

            <flux:switch wire:model="isActive" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
