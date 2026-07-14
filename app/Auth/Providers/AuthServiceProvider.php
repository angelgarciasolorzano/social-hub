<?php

declare(strict_types=1);

namespace App\Auth\Providers;

use App\Auth\Modules\TrustedDevice\Listeners\TrustedDeviceRemember;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->app->register(AuthRouteServiceProvider::class);

        $this->registerEventListeners();
    }

    /**
     * Register Fortify event listeners owned by this module.
     */
    private function registerEventListeners(): void
    {
        Event::listen(
            ValidTwoFactorAuthenticationCodeProvided::class,
            TrustedDeviceRemember::class,
        );
    }
}
