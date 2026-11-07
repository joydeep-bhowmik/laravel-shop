<?php

use App\Http\Controllers\Store\SitemapController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::store.home')->name('home');
Route::livewire('shop', 'pages::store.shop')->name('shop');
Route::livewire('search', 'pages::store.shop')->name('search');
Route::livewire('category/{categorySlug}', 'pages::store.shop')->name('category');
Route::livewire('brand/{brandSlug}', 'pages::store.shop')->name('brand');
Route::livewire('product/{productSlug}', 'pages::store.product')->name('product');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
