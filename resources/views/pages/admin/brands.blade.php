<?php

use function Laravel\Folio\{middleware, name};

name('admin.brands.index');
middleware(['can:catalog.manage']);
?>
<x-layouts::admin :title="__('Brands')">
    <livewire:admin.brands.index />
</x-layouts::admin>
