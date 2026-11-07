<?php

use App\Models\ProductReview;
use App\Services\ReviewService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /** @var array<int, string> */
    public array $selected = [];

    #[Url(except: 'pending')]
    public string $state = 'pending';

    #[Computed]
    public function reviews()
    {
        return ProductReview::query()
            ->with(['product', 'user'])
            ->when($this->state === 'pending', fn ($query) => $query->where('is_approved', false))
            ->when($this->state === 'approved', fn ($query) => $query->where('is_approved', true))
            ->latest()
            ->paginate(15);
    }

    public function updatedState(): void
    {
        $this->resetPage();
    }

    /**
     * Select every review on this page, or clear the selection when they are all selected.
     */
    public function toggleAll(): void
    {
        $pageIds = $this->reviews->getCollection()->pluck('id')->map(fn ($id): string => (string) $id)->all();

        $this->selected = $this->allOnPageSelected ? [] : $pageIds;
    }

    #[Computed]
    public function allOnPageSelected(): bool
    {
        $pageIds = $this->reviews->getCollection()->pluck('id')->map(fn ($id): string => (string) $id)->all();

        return $pageIds !== [] && count(array_intersect($pageIds, array_map('strval', $this->selected))) === count($pageIds);
    }

    public function bulkApprove(ReviewService $reviews): void
    {
        Gate::authorize('reviews.moderate');

        foreach (ProductReview::query()->whereIn('id', array_map('intval', $this->selected))->where('is_approved', false)->get() as $review) {
            $reviews->approve($review);
        }

        $this->reset('selected');
        unset($this->reviews);
        Flux::toast(variant: 'success', text: __('Selected reviews approved.'));
    }

    public function bulkRemove(ReviewService $reviews): void
    {
        Gate::authorize('reviews.moderate');

        foreach (ProductReview::query()->whereIn('id', array_map('intval', $this->selected))->get() as $review) {
            $reviews->delete($review);
        }

        $this->reset('selected');
        unset($this->reviews);
        Flux::toast(variant: 'success', text: __('Selected reviews removed.'));
    }

    public function approve(int $id, ReviewService $reviews): void
    {
        Gate::authorize('reviews.moderate');

        $reviews->approve(ProductReview::query()->findOrFail($id));
        Flux::toast(variant: 'success', text: __('Review approved.'));
    }

    public function remove(int $id, ReviewService $reviews): void
    {
        Gate::authorize('reviews.moderate');

        $reviews->delete(ProductReview::query()->findOrFail($id));
        Flux::toast(variant: 'success', text: __('Review removed.'));
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center gap-3">
        <label for="review-state" class="text-sm text-zinc-500">{{ __('Show') }}</label>
        <select id="review-state" wire:model.live="state" class="rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800">
            <option value="pending">{{ __('Waiting for approval') }}</option>
            <option value="approved">{{ __('Approved') }}</option>
            <option value="all">{{ __('All') }}</option>
        </select>
    </div>

    @if ($selected !== [])
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
            <flux:button variant="primary" wire:click="bulkApprove">{{ __('Approve selected') }}</flux:button>
            <x-confirm-button name="confirm-bulk-reviews" :label="__('Remove selected')" action="bulkRemove" :message="__('Remove the selected reviews?')" />
            <flux:text>{{ trans_choice(':count selected|:count selected', count($selected), ['count' => count($selected)]) }}</flux:text>
        </div>
    @endif

    <flux:table :paginate="$this->reviews">
        <flux:table.columns>
            <flux:table.column>
                <input type="checkbox" wire:click="toggleAll" @checked($this->allOnPageSelected) class="rounded border-zinc-300" aria-label="{{ __('Select all on this page') }}">
            </flux:table.column>
            <flux:table.column>{{ __('Product') }}</flux:table.column>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('Review') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->reviews as $review)
                <flux:table.row wire:key="review-{{ $review->id }}">
                    <flux:table.cell>
                        <input type="checkbox" value="{{ $review->id }}" wire:model="selected" class="rounded border-zinc-300" aria-label="{{ __('Select review') }}">
                    </flux:table.cell>
                    <flux:table.cell>{{ $review->product->name }}</flux:table.cell>
                    <flux:table.cell>{{ $review->user->name }}</flux:table.cell>
                    <flux:table.cell class="max-w-md">
                        <p class="font-medium">{{ $review->rating }}/5 · {{ $review->title }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $review->body }}</p>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$review->is_approved ? 'green' : 'amber'">{{ $review->is_approved ? __('Approved') : __('Pending') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            @unless ($review->is_approved)
                                <flux:button size="xs" variant="primary" wire:click="approve({{ $review->id }})">{{ __('Approve') }}</flux:button>
                            @endunless
                            <x-confirm-button name="confirm-review-{{ $review->id }}" size="xs" :label="__('Remove')" action="remove({{ $review->id }})" :message="__('Remove this review?')" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6"><flux:text>{{ __('Nothing to moderate.') }}</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
