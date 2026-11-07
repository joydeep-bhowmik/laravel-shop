<flux:modal name="confirm-logout" class="md:w-96">
    <form method="POST" action="{{ route('logout') }}" class="space-y-4">
        @csrf
        <flux:heading>{{ __('Log out?') }}</flux:heading>
        <flux:text>{{ __('You will need to log in again to see your account.') }}</flux:text>
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" x-on:click="$flux.modal('confirm-logout').close()">{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="danger">{{ __('Log out') }}</flux:button>
        </div>
    </form>
</flux:modal>
