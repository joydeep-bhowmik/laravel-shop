<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Services\SortsAndFiltersTables;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

use Livewire\Component;

new class extends Component {
    use SortsAndFiltersTables;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_variant = false;

    public ?int $selectedId = null;

    public string $newValue = '';

    public string $newColor = '';

    #[Computed]
    public function attributeList()
    {
        return $this->applyTableSort(
            $this->applyDateRange(Attribute::query()->withCount('values'), 'created_at'),
            ['position' => 'position', 'name' => 'name', 'type' => 'is_variant', 'values' => 'values_count', 'created_at' => 'created_at'],
            'position',
            'asc',
        )->get();
    }

    #[Computed]
    public function selected(): ?Attribute
    {
        return $this->selectedId === null ? null : Attribute::query()->with('values')->find($this->selectedId);
    }

    public function create(): void
    {
        Gate::authorize('catalog.manage');

        $this->reset('editingId', 'name', 'is_variant');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('catalog.manage');

        $attribute = Attribute::query()->findOrFail($id);

        $this->editingId = $attribute->id;
        $this->name = $attribute->name;
        $this->is_variant = $attribute->is_variant;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('catalog.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'is_variant' => ['boolean'],
        ]);

        $attribute = $this->editingId !== null ? Attribute::query()->findOrFail($this->editingId) : new Attribute;
        $attribute->fill($validated)->save();

        $this->showForm = false;
        $this->reset('editingId');
        Flux::toast(variant: 'success', text: __('Attribute saved.'));
    }

    public function delete(int $id): void
    {
        Gate::authorize('catalog.manage');

        Attribute::query()->findOrFail($id)->delete();

        if ($this->selectedId === $id) {
            $this->selectedId = null;
        }

        Flux::toast(variant: 'success', text: __('Attribute deleted.'));
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->reset('newValue', 'newColor');
        unset($this->selected);
    }

    public function addValue(): void
    {
        Gate::authorize('catalog.manage');

        $attribute = $this->selected;

        abort_if($attribute === null, 404);

        $validated = $this->validate([
            'newValue' => ['required', 'string', 'max:80'],
            'newColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $slug = Str::slug($validated['newValue']);

        if ($attribute->values()->where('slug', $slug)->exists()) {
            $this->addError('newValue', __('That value already exists.'));

            return;
        }

        $attribute->values()->create([
            'value' => $validated['newValue'],
            'slug' => $slug,
            'color_hex' => $validated['newColor'] ?: null,
            'position' => (int) $attribute->values()->max('position') + 1,
        ]);

        $this->reset('newValue', 'newColor');
        unset($this->selected);
    }

    public function removeValue(int $valueId): void
    {
        Gate::authorize('catalog.manage');

        AttributeValue::query()
            ->whereKey($valueId)
            ->where('attribute_id', $this->selectedId)
            ->delete();

        unset($this->selected);
    }
}; ?>
<div class="space-y-6">

<div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
    <div class="space-y-4">
        <div class="flex justify-end">
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New attribute') }}</flux:button>
        </div>

        <x-admin.date-range />
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sort === 'name'" :direction="$direction" wire:click="sortBy('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'type'" :direction="$direction" wire:click="sortBy('type')">{{ __('Type') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'values'" :direction="$direction" wire:click="sortBy('values')">{{ __('Values') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->attributeList as $attribute)
                    <flux:table.row wire:key="attribute-{{ $attribute->id }}">
                        <flux:table.cell variant="strong">{{ $attribute->name }}</flux:table.cell>
                        <flux:table.cell>{{ $attribute->is_variant ? __('Variant option') : __('Descriptive') }}</flux:table.cell>
                        <flux:table.cell>{{ $attribute->values_count }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" wire:click="select({{ $attribute->id }})">{{ __('Values') }}</flux:button>
                                <flux:button size="xs" wire:click="edit({{ $attribute->id }})">{{ __('Edit') }}</flux:button>
                                <x-confirm-button name="confirm-attribute-{{ $attribute->id }}" size="xs" :label="__('Delete')" action="delete({{ $attribute->id }})" :message="__('Delete this attribute and its values?')" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:card class="space-y-4">
        @if ($this->selected)
            <flux:heading size="lg">{{ $this->selected->name }}</flux:heading>
            <flux:text>{{ __('Values can be picked for variants and product attributes.') }}</flux:text>

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->selected->values as $value)
                    <li wire:key="value-{{ $value->id }}" class="flex items-center justify-between py-2">
                        <span class="flex items-center gap-2">
                            @if ($value->color_hex)
                                <span class="inline-block size-4 rounded-full border border-zinc-300" style="background-color: {{ $value->color_hex }}"></span>
                            @endif
                            {{ $value->value }}
                        </span>
                        <x-confirm-button name="confirm-value-{{ $value->id }}" size="xs" :label="__('Remove')" action="removeValue({{ $value->id }})" :message="__('Remove this value?')" />
                    </li>
                @empty
                    <li class="py-2"><flux:text>{{ __('No values yet.') }}</flux:text></li>
                @endforelse
            </ul>

            <form wire:submit="addValue" class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                <flux:input wire:model="newValue" :placeholder="__('Value, e.g. Medium')" />
                <flux:input wire:model="newColor" type="text" placeholder="#RRGGBB" class="w-32" />
                <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
                <flux:error name="newValue" />
                <flux:error name="newColor" />
            </form>
        @else
            <flux:text>{{ __('Select an attribute to manage its values.') }}</flux:text>
        @endif
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit attribute') : __('New attribute') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. Size, Colour, Fabric') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:switch wire:model="is_variant" :label="__('Used to build variants (size, colour)')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
