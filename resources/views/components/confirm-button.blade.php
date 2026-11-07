@props(['name', 'label', 'action', 'message', 'title' => null, 'variant' => 'danger', 'size' => null, 'triggerVariant' => null, 'triggerClass' => ''])

<flux:button size="{{ $size ?? 'base' }}" variant="{{ $triggerVariant ?? $variant }}" class="{{ $triggerClass }}" x-on:click="$flux.modal('{{ $name }}').show()">{{ $label }}</flux:button>

<flux:modal name="{{ $name }}" class="md:w-96">
    <div class="space-y-4">
        <flux:heading>{{ $title ?? __('Are you sure?') }}</flux:heading>
        <flux:text>{{ $message }}</flux:text>
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" x-on:click="$flux.modal('{{ $name }}').close()">{{ __('Cancel') }}</flux:button>
            <flux:button variant="{{ $variant }}" wire:click="{{ $action }}" x-on:click="$flux.modal('{{ $name }}').close()">{{ __('Confirm') }}</flux:button>
        </div>
    </div>
</flux:modal>
