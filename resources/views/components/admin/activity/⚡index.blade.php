<?php


use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new class extends Component {
    use WithPagination;

    #[Computed]
    public function entries()
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->latest('id')
            ->paginate(25);
    }
}; ?>

<div class="space-y-6">
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
                        @php($changes = collect($entry->properties->get('attributes', []))->keys()->implode(', '))
                        {{ $changes !== '' ? $changes : '-' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5"><flux:text>{{ __('Nothing recorded yet.') }}</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
