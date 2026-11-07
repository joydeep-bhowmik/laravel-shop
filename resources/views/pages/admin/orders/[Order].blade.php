<?php

use function Laravel\Folio\{middleware, name};

name('admin.orders.show');
middleware(['can:orders.view']);
?>
<x-layouts::admin :title="__('Order')">
    <livewire:admin.orders.show :order="$order->id" />
</x-layouts::admin>
