<?php

use function Laravel\Folio\{middleware, name};

name('admin.categories.index');
middleware(['can:catalog.manage']);
?>
<x-layouts::admin :title="__('Categories')">
    <livewire:admin.categories.index />
</x-layouts::admin>
