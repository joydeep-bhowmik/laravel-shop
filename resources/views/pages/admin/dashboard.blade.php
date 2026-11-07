<?php

use function Laravel\Folio\{middleware, name};

name('admin.dashboard');
middleware(['can:reports.view']);
?>
<x-layouts::admin :title="__('Dashboard')">
    <livewire:admin.dashboard />
</x-layouts::admin>
