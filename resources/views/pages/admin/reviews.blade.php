<?php

use function Laravel\Folio\{middleware, name};

name('admin.reviews.index');
middleware(['can:reviews.moderate']);
?>
<x-layouts::admin :title="__('Reviews')">
    <livewire:admin.reviews.index />
</x-layouts::admin>
