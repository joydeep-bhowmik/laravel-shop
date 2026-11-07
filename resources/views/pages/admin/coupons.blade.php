<?php

use function Laravel\Folio\{middleware, name};

name('admin.coupons.index');
middleware(['can:coupons.manage']);
?>
<x-layouts::admin :title="__('Coupons')">
    <livewire:admin.coupons.index />
</x-layouts::admin>
