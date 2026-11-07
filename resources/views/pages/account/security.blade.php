<?php

use function Laravel\Folio\{middleware, name};

name('account.security');
middleware(['auth', 'verified']);
?>
<x-layouts::app :title="__('Password and two-factor')">
    <livewire:account.security />
</x-layouts::app>
