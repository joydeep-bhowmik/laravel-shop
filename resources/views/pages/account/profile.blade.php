<?php

use function Laravel\Folio\{middleware, name};

name('account.profile');
middleware(['auth', 'verified.if-required']);
?>
<x-layouts::app :title="__('Profile')">
    <livewire:account.profile />
</x-layouts::app>
