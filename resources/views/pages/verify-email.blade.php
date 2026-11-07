<?php

use function Laravel\Folio\{middleware, name};

name('verification.notice');
middleware(['auth']);
?>
<x-layouts::app :title="__('Verify your email')">
    <livewire:auth.verify-notice />
</x-layouts::app>
