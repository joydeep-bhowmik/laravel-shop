<?php

use function Laravel\Folio\{middleware, name};

name('password.request');
middleware(['guest']);
?>
<x-layouts::app :title="__('Reset password')">
    <livewire:auth.forgot-password />
</x-layouts::app>
