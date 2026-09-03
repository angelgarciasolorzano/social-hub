<?php

declare(strict_types=1);

namespace App\Auth\Providers;

use App\Auth\Console\Commands\TrustedDevicePurge;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Override;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/trusted-devices.php',
            'module.auth.trusted_devices',
        );

        $this->app->register(AuthRouteServiceProvider::class);
        $this->app->register(AuthEventServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([TrustedDevicePurge::class]);
        }

        $this->registerTrustedDeviceSchedule();
    }

    /**
     * Schedule the daily purge of soft-deleted trusted devices past the
     * configured retention window. Runs at 03:00 server local time.
     */
    private function registerTrustedDeviceSchedule(): void
    {
        Schedule::command('trusted-devices:purge')
            ->dailyAt('03:00');
    }
}
