<?php

use function Laravel\Folio\name;

name('home');
?>
<x-layouts::app :title="__('Shop online')">
    <livewire:store.home />
</x-layouts::app>
