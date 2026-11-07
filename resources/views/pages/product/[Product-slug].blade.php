<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Contracts\View\View;

use function Laravel\Folio\{name, render};

name('product');

render(function (View $view, Product $product) {
    abort_unless(
        $product->status === ProductStatus::Published
            && ($product->published_at === null || $product->published_at->isPast()),
        404,
    );

    return $view;
});
?>
@php
    $description = $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 155);
    $image = $product->getFirstMediaUrl('gallery', 'detail') ?: asset('images/placeholder-product.svg');
@endphp

@push('head')
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $product->name }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:type" content="product">
    <link rel="canonical" href="{{ route('product', ['product' => $product->slug]) }}">
@endpush

<x-layouts::app :title="$product->name">
    <livewire:store.product :product-id="$product->id" />
</x-layouts::app>
