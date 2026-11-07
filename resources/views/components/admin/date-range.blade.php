<div class="flex flex-wrap items-end gap-3">
    <flux:field class="w-44">
        <flux:label>{{ __('From') }}</flux:label>
        <flux:input type="date" wire:model.live="dateFrom" />
    </flux:field>
    <flux:field class="w-44">
        <flux:label>{{ __('To') }}</flux:label>
        <flux:input type="date" wire:model.live="dateTo" />
    </flux:field>
    <flux:button variant="ghost" wire:click="clearDates">{{ __('Clear dates') }}</flux:button>
</div>
