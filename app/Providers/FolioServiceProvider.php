<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Folio\Folio;

class FolioServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Mount the pages directory. Storefront pages sit at the top level, admin pages under pages/admin.
     *
     * The admin area is protected by URL pattern, so the same mount covers both areas.
     */
    public function boot(): void
    {
        Folio::path(resource_path('views/pages'))
            ->uri('/')
            ->middleware([
                'admin' => ['auth', 'verified', 'can:admin.access'],
                'admin/*' => ['auth', 'verified', 'can:admin.access'],
            ]);
    }
}
