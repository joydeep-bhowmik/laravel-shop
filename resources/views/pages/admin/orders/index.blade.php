<?php

use function Laravel\Folio\{middleware, name};

name('admin.orders.index');
middleware(['can:orders.view']);
?>
<x-layouts::admin :title="__('Orders')">
    <livewire:admin.orders.index />
</x-layouts::admin>
