<?php

use App\Services\SettingsService;
use App\Services\SortsAndFiltersTables;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new class extends Component {
    use SortsAndFiltersTables, WithPagination;

    #[Computed]
    public function loggingEnabled(): bool
    {
        return app(SettingsService::class)->bool('activity.enabled');
    }

    #[Computed]
    public function entries()
    {
        $query = Activity::query()->with(['causer', 'subject']);

        return $this->applyTableSort($this->applyDateRange($query, 'created_at'), [
            'when' => 'created_at',
            'event' => 'description',
        ])->paginate(25);
    }
}; ?>

<div class="space-y-6">
    @unless ($this->loggingEnabled)
        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>{{ __('Activity logging is off') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Changes are not being recorded. Turn it on in') }}
                <a href="{{ route('admin.settings.index') }}" wire:navigate class="underline">{{ __('Settings') }}</a>.
            </flux:callout.text>
        </flux:callout>
    @endunless

    <x-admin.date-range />

    <flux:table :paginate="$this->entries">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sort === 'when'" :direction="$direction" wire:click="sortBy('when')">{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Who') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'event'" :direction="$direction" wire:click="sortBy('event')">{{ __('Event') }}</flux:table.column>
            <flux:table.column>{{ __('Subject') }}</flux:table.column>
            <flux:table.column>{{ __('Changes') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->entries as $entry)
                <flux:table.row wire:key="activity-{{ $entry->id }}">
                    <flux:table.cell>{{ $entry->created_at->format('d M Y, H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->causer?->name ?? __('System') }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->description }}</flux:table.cell>
                    <flux:table.cell>{{ class_basename($entry->subject_type ?? '') }} {{ $entry->subject_id }}</flux:table.cell>
                    <flux:table.cell>
                        @php($changes = collect($entry->properties?->get('attributes', []))->keys()->implode(', '))
                        {{ $changes !== '' ? $changes : '-' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5"><flux:text>{{ __('Nothing recorded for this period.') }}</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
