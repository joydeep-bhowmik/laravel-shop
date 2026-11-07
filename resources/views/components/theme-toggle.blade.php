<button
    type="button"
    class="inline-flex size-9 items-center justify-center rounded-md text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 focus:outline-none focus-visible:ring-2 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
    x-on:click="$flux.appearance = $flux.dark ? 'light' : 'dark'"
    x-bind:aria-label="$flux.dark ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'"
    title="{{ __('Toggle dark mode') }}"
>
    <flux:icon name="sun" class="size-5" x-show="$flux.dark" />
    <flux:icon name="moon" class="size-5" x-show="! $flux.dark" />
</button>
