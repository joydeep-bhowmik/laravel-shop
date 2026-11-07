<?php

use function Laravel\Folio\name;

name('two-factor.challenge');
?>
<x-layouts::app :title="__('Two-factor check')">
    <livewire:auth.two-factor-challenge />
</x-layouts::app>
