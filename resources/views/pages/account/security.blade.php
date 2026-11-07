<?php

use function Laravel\Folio\{middleware, name};

name('account.security');
middleware(['auth', 'verified.if-required']);
?>
<x-layouts::app :title="__('Password and two-factor')">
    <livewire:account.security />
</x-layouts::app>
