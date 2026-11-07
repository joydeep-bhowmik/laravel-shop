<?php

namespace App\Providers;

use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Support\ActivityLogStatus;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureActivityLogging();
    }

    /**
     * Honour the admin "activity log" setting. Off unless an admin turns it on.
     *
     * Falls back to off when settings cannot be read yet, such as before the migrations have run.
     */
    protected function configureActivityLogging(): void
    {
        $this->app->extend(ActivityLogStatus::class, function (ActivityLogStatus $status, $app): ActivityLogStatus {
            try {
                $enabled = $app->make(SettingsService::class)->bool('activity.enabled');
            } catch (Throwable) {
                $enabled = false;
            }

            if (! $enabled) {
                $status->disable();
            }

            return $status;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
