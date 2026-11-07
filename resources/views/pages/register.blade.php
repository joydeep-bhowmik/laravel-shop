<?php

use function Laravel\Folio\{middleware, name};

name('register');
middleware(['guest']);
?>
<x-layouts::app :title="__('Create account')">
    <livewire:auth.register />
</x-layouts::app>
