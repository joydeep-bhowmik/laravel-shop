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

    <flux:table :paginate="$this->reviews">
        <flux:table.columns>
            <flux:table.column>{{ __('Product') }}</flux:table.column>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('Review') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->reviews as $review)
                <flux:table.row wire:key="review-{{ $review->id }}">
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
                            <flux:button size="xs" variant="danger" wire:click="remove({{ $review->id }})" wire:confirm="{{ __('Remove this review?') }}">{{ __('Remove') }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5"><flux:text>{{ __('Nothing to moderate.') }}</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
