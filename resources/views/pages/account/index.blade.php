<?php

use function Laravel\Folio\{middleware, name};

name('dashboard');
middleware(['auth', 'verified']);
?>
<x-layouts::app :title="__('My account')">
    <div class="grid max-w-2xl gap-4">
        <p class="text-zinc-600 dark:text-zinc-400">{{ __('Hello, :name.', ['name' => auth()->user()->name]) }}</p>
        <ul class="grid gap-3 sm:grid-cols-2">
            <li><a href="{{ route('account.profile') }}" wire:navigate class="block rounded-xl border border-zinc-200 p-5 font-medium hover:border-zinc-900 dark:border-zinc-700">{{ __('Profile') }}</a></li>
            <li><a href="{{ route('account.security') }}" wire:navigate class="block rounded-xl border border-zinc-200 p-5 font-medium hover:border-zinc-900 dark:border-zinc-700">{{ __('Password and two-factor') }}</a></li>
        </ul>
    </div>
</x-layouts::app>
