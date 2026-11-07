<?php

use App\Http\Controllers\Admin\ProductExportController;
use Illuminate\Support\Facades\Route;

// Admin pages are Folio routes in resources/views/pages/admin. Only non-page endpoints live here.
Route::middleware(['auth', 'verified', 'can:admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('products/export', ProductExportController::class)
            ->middleware('can:products.import')
            ->name('products.export');
    });
