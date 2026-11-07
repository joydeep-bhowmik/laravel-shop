<?php

use App\Enums\AdminRole;
use App\Models\User;
use App\Services\CustomerAdminService;
use App\Services\SortsAndFiltersTables;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new class extends Component {
    use SortsAndFiltersTables, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Empty for everyone, "customers" for accounts with no admin role, or a role name. */
    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    public ?int $editingId = null;

    /** @var array<int, string> */
    public array $selectedRoles = [];

    public bool $showRoles = false;

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->roleFilter === 'customers', fn ($query) => $query->whereDoesntHave('roles'))
            ->when($this->roleFilter !== '' && $this->roleFilter !== 'customers', fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $this->roleFilter)))
            ->tap(fn ($query) => $this->applyDateRange($query, 'created_at'))
            ->tap(fn ($query) => $this->applyTableSort($query, [
                'name' => 'name',
                'email' => 'email',
                'status' => 'is_blocked',
                'joined' => 'created_at',
            ], 'joined'))
            ->paginate(15);
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->orderBy('name')->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Human label for a role: built-in labels come from AdminRole, custom roles show their name.
     */
    public function roleLabel(Role $role): string
    {
        return AdminRole::tryFrom($role->name)?->label() ?? ucwords(str_replace('-', ' ', $role->name));
    }

    public function openRoles(int $id): void
    {
        Gate::authorize('users.manage');

        $user = User::query()->with('roles')->findOrFail($id);

        $this->editingId = $user->id;
        $this->selectedRoles = $user->roles->pluck('name')->values()->all();
        $this->showRoles = true;
    }

    public function saveRoles(CustomerAdminService $customers): void
    {
        Gate::authorize('users.manage');

        $this->validate([
            'selectedRoles' => ['array'],
            'selectedRoles.*' => [Rule::exists('roles', 'name')],
        ]);

        $target = User::query()->findOrFail($this->editingId);

        try {
            $customers->syncRoles(Auth::user(), $target, $this->selectedRoles);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->showRoles = false;
        Flux::toast(variant: 'success', text: __('Roles updated.'));
    }

    public function toggleBlock(int $id, CustomerAdminService $customers): void
    {
        $target = User::query()->findOrFail($id);

        try {
            $customers->setBlocked(Auth::user(), $target, ! $target->is_blocked);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: $target->is_blocked ? __('Account blocked.') : __('Account unblocked.'));
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name or email')" class="w-72" />
            <x-admin.date-range />
            <flux:select wire:model.live="roleFilter" class="w-48">
                <flux:select.option value="">{{ __('Everyone') }}</flux:select.option>
                <flux:select.option value="customers">{{ __('Customers only') }}</flux:select.option>
                @foreach ($this->roles as $role)
                    <flux:select.option value="{{ $role->name }}">{{ $this->roleLabel($role) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sort === 'name'" :direction="$direction" wire:click="sortBy('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Roles') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'status'" :direction="$direction" wire:click="sortBy('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'joined'" :direction="$direction" wire:click="sortBy('joined')">{{ __('Joined') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}">
                    <flux:table.cell variant="strong">
                        <a href="{{ route('admin.customers.show', ['user' => $user]) }}" wire:navigate class="underline-offset-2 hover:underline">{{ $user->name }}</a>
                        <div class="text-xs font-normal text-zinc-500">{{ $user->email }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($user->roles as $role)
                                <flux:badge size="sm" color="blue">{{ $this->roleLabel($role) }}</flux:badge>
                            @empty
                                <flux:text class="text-xs">{{ __('Customer') }}</flux:text>
                            @endforelse
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$user->is_blocked ? 'red' : 'green'">{{ $user->is_blocked ? __('Blocked') : __('Active') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->created_at->format('d M Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            @can('users.manage')
                                @unless ($user->is(auth()->user()))
                                    <flux:button size="xs" wire:click="openRoles({{ $user->id }})">{{ __('Roles') }}</flux:button>
                                @endunless
                            @endcan
                            @can('customers.block')
                                @unless ($user->is(auth()->user()))
                                    <x-confirm-button
                                        name="confirm-block-user-{{ $user->id }}"
                                        size="xs"
                                        :variant="$user->is_blocked ? 'primary' : 'danger'"
                                        :label="$user->is_blocked ? __('Unblock') : __('Block')"
                                        action="toggleBlock({{ $user->id }})"
                                        :title="$user->is_blocked ? __('Unblock this account?') : __('Block this account?')"
                                        :message="$user->is_blocked ? __('The customer can sign in again.') : __('The customer will be signed out and cannot sign in until unblocked.')"
                                    />
                                @endunless
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5"><flux:text>{{ __('No users match.') }}</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @can('users.manage')
        <flux:modal wire:model="showRoles" class="md:w-[28rem]">
            <form wire:submit="saveRoles" class="space-y-4">
                <flux:heading size="lg">{{ __('Roles') }}</flux:heading>
                <flux:description>{{ __('Choose the roles this account has. Any role with a permission gives admin panel access.') }}</flux:description>

                <div class="space-y-2">
                    @foreach ($this->roles as $role)
                        <flux:checkbox wire:model="selectedRoles" value="{{ $role->name }}" :label="$this->roleLabel($role)" />
                    @endforeach
                </div>
                <flux:error name="selectedRoles" />

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showRoles', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save roles') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
