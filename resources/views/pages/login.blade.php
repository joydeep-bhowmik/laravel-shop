<?php

use function Laravel\Folio\{middleware, name};

name('login');
middleware(['guest']);
?>
<x-layouts::app :title="__('Log in')">
    <livewire:auth.login />
</x-layouts::app>
