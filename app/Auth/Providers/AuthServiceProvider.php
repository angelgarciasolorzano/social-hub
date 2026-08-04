<?php

declare(strict_types=1);

namespace App\Auth\Providers;

use App\Auth\Models\TrustedDevice;
use Carbon\CarbonImmutable;
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

        $this->registerTrustedDeviceSchedule();
    }

    /**
     * Register scheduled tasks owned by the TrustedDevice module.
     */
    private function registerTrustedDeviceSchedule(): void
    {
        Schedule::call(function (): void {
            TrustedDevice::query()
                ->where('expires_at', '<', CarbonImmutable::now()->subDays(30))
                ->delete();
        })->daily();
    }
}
