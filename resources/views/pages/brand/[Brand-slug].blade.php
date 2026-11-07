<?php

use App\Models\Brand;
use Illuminate\Contracts\View\View;

use function Laravel\Folio\{name, render};

name('brand');

render(function (View $view, Brand $brand) {
    abort_unless($brand->is_active, 404);

    return $view;
});
?>
@push('head')
    <meta name="description" content="{{ __('Shop :name products online, with cash on delivery and easy returns.', ['name' => $brand->name]) }}">
    <link rel="canonical" href="{{ route('brand', ['brand' => $brand->slug]) }}">
@endpush

<x-layouts::app :title="$brand->name">
    <livewire:store.shop :brand-id="$brand->id" />
</x-layouts::app>
