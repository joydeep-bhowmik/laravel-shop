<?php

use function Laravel\Folio\{middleware, name};

name('admin.products.create');
middleware(['can:products.create']);
?>
<x-layouts::admin :title="__('New product')">
    <livewire:admin.products.form />
</x-layouts::admin>
