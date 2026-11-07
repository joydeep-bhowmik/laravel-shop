<?php

use function Laravel\Folio\{middleware, name};

name('admin.products.edit');
middleware(['can:products.update']);
?>
<x-layouts::admin :title="__('Edit product')">
    <livewire:admin.products.form :product="$product->id" />
</x-layouts::admin>
