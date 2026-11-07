<?php

use function Laravel\Folio\{middleware, name};

name('admin.users.index');
middleware(['can:customers.view']);
?>
<x-layouts::admin :title="__('Users')">
    <livewire:admin.users.index />
</x-layouts::admin>
