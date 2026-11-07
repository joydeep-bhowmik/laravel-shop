<?php

use function Laravel\Folio\name;

name('shop');
?>
<x-layouts::app :title="__('Shop')">
    <livewire:store.shop />
</x-layouts::app>
