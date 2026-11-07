<?php

use App\Models\Brand;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Brands')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    #[Computed]
    public function brands()
    {
        return Brand::query()->withCount('products')->orderBy('name')->paginate(15);
    }

    public function create(): void
    {
        Gate::authorize('catalog.manage');

        $this->reset('editingId', 'name', 'description');
        $this->is_active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('catalog.manage');

        $brand = Brand::query()->findOrFail($id);

        $this->editingId = $brand->id;
        $this->name = $brand->name;
        $this->description = (string) $brand->description;
        $this->is_active = $brand->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('catalog.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $brand = $this->editingId !== null ? Brand::query()->findOrFail($this->editingId) : new Brand;
        $brand->fill($validated)->save();

        $this->showForm = false;
        $this->reset('editingId');
        Flux::toast(variant: 'success', text: __('Brand saved.'));
    }

    public function delete(int $id): void
    {
        Gate::authorize('catalog.manage');

        $brand = Brand::query()->withCount('products')->findOrFail($id);

        if ($brand->products_count > 0) {
            Flux::toast(variant: 'danger', text: __('Brands with products cannot be deleted. Hide the brand instead.'));

            return;
        }

        $brand->delete();
        Flux::toast(variant: 'success', text: __('Brand deleted.'));
    }
}; ?>
<div class="space-y-6">

<div class="space-y-4">
    <div class="flex justify-end">
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New brand') }}</flux:button>
    </div>

    <flux:table :paginate="$this->brands">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Products') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->brands as $brand)
                <flux:table.row wire:key="brand-{{ $brand->id }}">
                    <flux:table.cell variant="strong">{{ $brand->name }}</flux:table.cell>
                    <flux:table.cell>{{ $brand->products_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$brand->is_active ? 'green' : 'zinc'">
                            {{ $brand->is_active ? __('Active') : __('Hidden') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="xs" wire:click="edit({{ $brand->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="xs" variant="danger" wire:click="delete({{ $brand->id }})" wire:confirm="{{ __('Delete this brand?') }}">{{ __('Delete') }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showForm" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit brand') : __('New brand') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="description" rows="3" />
                <flux:error name="description" />
            </flux:field>

            <flux:switch wire:model="is_active" :label="__('Visible on storefront')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
