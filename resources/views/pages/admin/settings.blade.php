<?php

use function Laravel\Folio\{middleware, name};

name('admin.settings.index');
middleware(['can:settings.manage']);
?>
<x-layouts::admin :title="__('Settings')">
    <livewire:admin.settings.index />
</x-layouts::admin>
