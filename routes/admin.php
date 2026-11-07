<?php

use App\Http\Controllers\Admin\ProductExportController;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route(Gate::allows('reports.view') ? 'admin.dashboard' : 'admin.orders.index'))
            ->name('home');

        Route::livewire('dashboard', 'pages::admin.dashboard')
            ->middleware('can:reports.view')
            ->name('dashboard');

        Route::livewire('categories', 'pages::admin.categories.index')
            ->middleware('can:catalog.manage')
            ->name('categories.index');

        Route::livewire('brands', 'pages::admin.brands.index')
            ->middleware('can:catalog.manage')
            ->name('brands.index');

        Route::livewire('attributes', 'pages::admin.attributes.index')
            ->middleware('can:catalog.manage')
            ->name('attributes.index');

        Route::livewire('products', 'pages::admin.products.index')
            ->middleware('can:products.view')
            ->name('products.index');

        Route::get('products/export', ProductExportController::class)
            ->middleware('can:products.import')
            ->name('products.export');

        Route::livewire('products/create', 'pages::admin.products.form')
            ->middleware('can:products.create')
            ->name('products.create');

        Route::livewire('products/{product}/edit', 'pages::admin.products.form')
            ->middleware('can:products.update')
            ->name('products.edit');

        Route::livewire('inventory', 'pages::admin.inventory.index')
            ->middleware('can:products.view')
            ->name('inventory.index');

        Route::livewire('orders', 'pages::admin.orders.index')
            ->middleware('can:orders.view')
            ->name('orders.index');

        Route::livewire('orders/{order}', 'pages::admin.orders.show')
            ->middleware('can:orders.view')
            ->name('orders.show');

        Route::livewire('reviews', 'pages::admin.reviews.index')
            ->middleware('can:reviews.moderate')
            ->name('reviews.index');

        Route::livewire('settings', 'pages::admin.settings.index')
            ->middleware('can:settings.manage')
            ->name('settings.index');

        Route::livewire('activity', 'pages::admin.activity.index')
            ->middleware('can:activity.view')
            ->name('activity.index');
    });
