<?php

use App\Http\Controllers\Store\SitemapController;
use Illuminate\Support\Facades\Route;

// Storefront pages are Folio routes in resources/views/pages/store. Only non-page endpoints live here.
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
