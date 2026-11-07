<?php

use function Laravel\Folio\{middleware, name};

name('admin.activity.index');
middleware(['can:activity.view']);
?>
<x-layouts::admin :title="__('Activity log')">
    <livewire:admin.activity.index />
</x-layouts::admin>
