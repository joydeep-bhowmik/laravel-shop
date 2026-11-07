<?php

use function Laravel\Folio\{middleware, name};

name('admin.products.index');
middleware(['can:products.view']);
?>
<x-layouts::admin :title="__('Products')">
    <livewire:admin.products.index />
</x-layouts::admin>
