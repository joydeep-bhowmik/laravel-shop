<?php

use function Laravel\Folio\{middleware, name};

name('admin.attributes.index');
middleware(['can:catalog.manage']);
?>
<x-layouts::admin :title="__('Attributes')">
    <livewire:admin.attributes.index />
</x-layouts::admin>
