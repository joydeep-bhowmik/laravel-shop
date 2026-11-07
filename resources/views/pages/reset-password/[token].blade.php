<?php

use function Laravel\Folio\{middleware, name};

name('password.reset');
middleware(['guest']);
?>
<x-layouts::app :title="__('Choose a new password')">
    <livewire:auth.reset-password :token="$token" />
</x-layouts::app>
