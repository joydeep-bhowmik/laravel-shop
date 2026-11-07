<?php

use App\Models\Category;
use Illuminate\Contracts\View\View;

use function Laravel\Folio\{name, render};

name('category');

render(function (View $view, Category $category) {
    abort_unless($category->is_active, 404);

    return $view;
});
?>
@php
    $description = $category->meta_description ?: ($category->description ?: __('Shop :name online with cash on delivery and easy returns.', ['name' => $category->name]));
@endphp

@push('head')
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($description, 155) }}">
    <link rel="canonical" href="{{ route('category', ['category' => $category->slug]) }}">
@endpush

<x-layouts::app :title="$category->name">
    <livewire:store.shop :category-id="$category->id" />
</x-layouts::app>
