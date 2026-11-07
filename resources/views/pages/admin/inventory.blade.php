<?php

use function Laravel\Folio\{middleware, name};

name('admin.inventory.index');
middleware(['can:products.view']);
?>
<x-layouts::admin :title="__('Inventory')">
    <livewire:admin.inventory.index />
</x-layouts::admin>
