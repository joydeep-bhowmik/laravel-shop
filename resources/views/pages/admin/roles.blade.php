<?php

use function Laravel\Folio\{middleware, name};

name('admin.roles.index');
middleware(['can:roles.manage']);
?>
<x-layouts::admin :title="__('Roles and permissions')">
    <livewire:admin.roles.index />
</x-layouts::admin>
