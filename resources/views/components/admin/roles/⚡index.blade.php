<?php

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Services\AdminRoleService;
use App\Services\SortsAndFiltersTables;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component {
    use SortsAndFiltersTables;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    #[Computed]
    public function roles()
    {
        return $this->applyTableSort(
            $this->applyDateRange(Role::query()->with('permissions')->withCount('users'), 'created_at'),
            ['name' => 'name', 'users' => 'users_count', 'created_at' => 'created_at'],
            'name',
            'asc',
        )->get();
    }

    /**
     * @return array<string, array<int, AdminPermission>> Permissions grouped by area, such as "orders".
     */
    #[Computed]
    public function permissionGroups(): array
    {
        $groups = [];

        foreach (AdminPermission::cases() as $permission) {
            $groups[Str::before($permission->value, '.')][] = $permission;
        }

        return $groups;
    }

    public function isBuiltIn(Role $role): bool
    {
        return in_array($role->name, AdminRoleService::builtInNames(), true);
    }

    public function roleLabel(Role $role): string
    {
        return AdminRole::tryFrom($role->name)?->label() ?? ucwords(str_replace('-', ' ', $role->name));
    }

    public function create(): void
    {
        Gate::authorize('roles.manage');

        $this->reset('editingId', 'name', 'selectedPermissions');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('roles.manage');

        $role = Role::query()->with('permissions')->findOrFail($id);

        if ($this->isBuiltIn($role)) {
            Flux::toast(variant: 'warning', text: __('Built-in roles cannot be changed.'));

            return;
        }

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->values()->all();
        $this->showForm = true;
    }

    public function save(AdminRoleService $roles): void
    {
        Gate::authorize('roles.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'selectedPermissions' => ['array'],
        ]);

        try {
            if ($this->editingId === null) {
                $roles->create($this->name, $this->selectedPermissions);
            } else {
                $roles->update(Role::query()->findOrFail($this->editingId), $this->name, $this->selectedPermissions);
            }
        } catch (\InvalidArgumentException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $this->showForm = false;
        Flux::toast(variant: 'success', text: __('Role saved.'));
    }

    public function delete(int $id, AdminRoleService $roles): void
    {
        try {
            $roles->delete(Role::query()->findOrFail($id));
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Role deleted.'));
    }
}; ?>

<div class="space-y-4">
    <div class="flex justify-end">
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New role') }}</flux:button>
    </div>

    <x-admin.date-range />
    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sort === 'name'" :direction="$direction" wire:click="sortBy('name')">{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Permissions') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'users'" :direction="$direction" wire:click="sortBy('users')">{{ __('Users') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->roles as $role)
                <flux:table.row wire:key="role-{{ $role->id }}">
                    <flux:table.cell variant="strong">
                        {{ $this->roleLabel($role) }}
                        <div class="text-xs font-normal text-zinc-500">
                            {{ $this->isBuiltIn($role) ? __('Built-in') : __('Custom') }} · {{ $role->name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($role->name === AdminRole::SuperAdmin->value)
                            <flux:text class="text-sm">{{ __('All permissions') }}</flux:text>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @forelse ($role->permissions as $permission)
                                    <flux:badge size="sm" color="zinc">{{ AdminPermission::tryFrom($permission->name)?->label() ?? $permission->name }}</flux:badge>
                                @empty
                                    <flux:text class="text-sm">{{ __('None') }}</flux:text>
                                @endforelse
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $role->users_count }}</flux:table.cell>
                    <flux:table.cell>
                        @unless ($this->isBuiltIn($role))
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" wire:click="edit({{ $role->id }})">{{ __('Edit') }}</flux:button>
                                <x-confirm-button
                                    name="confirm-delete-role-{{ $role->id }}"
                                    size="xs"
                                    :label="__('Delete')"
                                    action="delete({{ $role->id }})"
                                    :message="__('Delete this role? Roles still assigned to users cannot be deleted.')"
                                />
                            </div>
                        @endunless
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showForm" class="md:w-[40rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit role') : __('New role') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Role name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('Content editor') }}" />
                <flux:error name="name" />
            </flux:field>

            <div class="space-y-4">
                <flux:label>{{ __('Permissions') }}</flux:label>
                @foreach ($this->permissionGroups as $area => $permissions)
                    <div class="space-y-2">
                        <flux:heading size="sm" class="capitalize">{{ $area }}</flux:heading>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                <flux:checkbox wire:model="selectedPermissions" value="{{ $permission->value }}" :label="$permission->label()" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <flux:error name="selectedPermissions" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save role') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
