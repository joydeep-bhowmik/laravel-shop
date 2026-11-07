<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categories')] #[Layout('layouts::admin')] class extends Component {
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $parent_id = null;

    public string $description = '';

    public string $meta_title = '';

    public string $meta_description = '';

    public bool $is_active = true;

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->with('parent')
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function parentOptions()
    {
        return Category::query()
            ->whereNull('parent_id')
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        Gate::authorize('catalog.manage');

        $this->reset('editingId', 'name', 'parent_id', 'description', 'meta_title', 'meta_description');
        $this->is_active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('catalog.manage');

        $category = Category::query()->findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parent_id = $category->parent_id;
        $this->description = (string) $category->description;
        $this->meta_title = (string) $category->meta_title;
        $this->meta_description = (string) $category->meta_description;
        $this->is_active = $category->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('catalog.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId !== null && (int) $validated['parent_id'] === $this->editingId) {
            $this->addError('parent_id', 'A category cannot be its own parent.');

            return;
        }

        $category = $this->editingId !== null ? Category::query()->findOrFail($this->editingId) : new Category;

        $category->fill($validated);

        if (! $category->exists) {
            $category->position = (int) Category::query()->max('position') + 1;
        }

        $category->save();

        $this->showForm = false;
        $this->reset('editingId');
        Flux::toast(variant: 'success', text: __('Category saved.'));
    }

    public function delete(int $id): void
    {
        Gate::authorize('catalog.manage');

        $category = Category::query()->withCount(['products', 'children'])->findOrFail($id);

        if ($category->products_count > 0 || $category->children_count > 0) {
            Flux::toast(variant: 'danger', text: __('Move or remove the products and subcategories first.'));

            return;
        }

        $category->delete();
        Flux::toast(variant: 'success', text: __('Category deleted.'));
    }

    /**
     * Persist a drag-and-drop reorder from wire:sort.
     */
    public function reorder(int $id, int $position): void
    {
        Gate::authorize('catalog.manage');

        $ids = Category::query()->orderBy('position')->orderBy('name')->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn (int $categoryId): bool => $categoryId !== $id));
        array_splice($ids, $position, 0, [$id]);

        foreach ($ids as $index => $categoryId) {
            Category::query()->whereKey($categoryId)->update(['position' => $index]);
        }

        unset($this->categories);
    }
}; ?>
<div class="space-y-6">

<div class="space-y-4">
    <div class="flex items-center justify-between gap-4">
        <flux:text>{{ __('Drag rows to change the order shown on the storefront.') }}</flux:text>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New category') }}</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column></flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Parent') }}</flux:table.column>
            <flux:table.column>{{ __('Products') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows wire:sort="reorder">
            @foreach ($this->categories as $category)
                <flux:table.row wire:key="category-{{ $category->id }}" wire:sort:item="{{ $category->id }}">
                    <flux:table.cell>
                        <flux:icon name="bars-2" variant="micro" class="cursor-grab text-zinc-400" wire:sort:handle />
                    </flux:table.cell>
                    <flux:table.cell variant="strong">{{ $category->name }}</flux:table.cell>
                    <flux:table.cell>{{ $category->parent?->name ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $category->products_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$category->is_active ? 'green' : 'zinc'">
                            {{ $category->is_active ? __('Active') : __('Hidden') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="xs" wire:click="edit({{ $category->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="xs" variant="danger" wire:click="delete({{ $category->id }})" wire:confirm="{{ __('Delete this category?') }}">{{ __('Delete') }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showForm" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit category') : __('New category') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Parent category') }}</flux:label>
                <flux:select wire:model="parent_id" placeholder="{{ __('None (top level)') }}">
                    @foreach ($this->parentOptions as $parent)
                        <flux:select.option value="{{ $parent->id }}">{{ $parent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="parent_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="description" rows="3" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Meta title') }}</flux:label>
                <flux:input wire:model="meta_title" />
                <flux:error name="meta_title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Meta description') }}</flux:label>
                <flux:textarea wire:model="meta_description" rows="2" />
                <flux:error name="meta_description" />
            </flux:field>

            <flux:switch wire:model="is_active" :label="__('Visible on storefront')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
