<?php

use App\Services\SettingsService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new class extends Component {
    use WithPagination;

    /** First day to include, as Y-m-d. Empty means no lower bound. */
    #[Url(as: 'from', except: '')]
    public string $from = '';

    /** Last day to include, as Y-m-d. Empty means no upper bound. */
    #[Url(as: 'to', except: '')]
    public string $to = '';

    public function updatedFrom(): void
    {
        $this->validateDates();
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->validateDates();
        $this->resetPage();
    }

    private function validateDates(): void
    {
        $this->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    public function clearDates(): void
    {
        $this->reset('from', 'to');
        $this->resetPage();
    }

    #[Computed]
    public function loggingEnabled(): bool
    {
        return app(SettingsService::class)->bool('activity.enabled');
    }

    #[Computed]
    public function entries()
    {
        $from = $this->parseDate($this->from);
        $to = $this->parseDate($this->to);

        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($from !== null, fn ($query) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($to !== null, fn ($query) => $query->where('created_at', '<=', $to->endOfDay()))
            ->latest('id')
            ->paginate(25);
    }

    /**
     * Ignore anything that is not a date, such as a hand-edited URL.
     */
    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
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

    <div class="flex flex-wrap items-end gap-3">
        <flux:field class="w-44">
            <flux:label>{{ __('From') }}</flux:label>
            <flux:input type="date" wire:model.live="from" />
            <flux:error name="from" />
        </flux:field>
        <flux:field class="w-44">
            <flux:label>{{ __('To') }}</flux:label>
            <flux:input type="date" wire:model.live="to" />
            <flux:error name="to" />
        </flux:field>
        @if ($from !== '' || $to !== '')
            <flux:button variant="ghost" wire:click="clearDates">{{ __('Clear dates') }}</flux:button>
        @endif
    </div>

    <flux:table :paginate="$this->entries">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Who') }}</flux:table.column>
            <flux:table.column>{{ __('Event') }}</flux:table.column>
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
