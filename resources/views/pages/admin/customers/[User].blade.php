<?php

use function Laravel\Folio\{middleware, name};

name('admin.customers.show');
middleware(['can:customers.view']);
?>
<x-layouts::admin :title="__('Customer')">
    <livewire:admin.customers.show :customer="$user->id" />
</x-layouts::admin>
