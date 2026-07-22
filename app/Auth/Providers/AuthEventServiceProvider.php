<?php

declare(strict_types=1);

namespace App\Auth\Providers;

use App\Auth\Modules\TrustedDevice\Listeners\TrustedDeviceInvalidate;
use App\Auth\Modules\TrustedDevice\Listeners\TrustedDeviceRemember;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

class AuthEventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerTrustedDeviceListeners();
    }

    /**
     * Register Fortify event listeners owned by this module.
     */
    private function registerTrustedDeviceListeners(): void
    {
        Event::listen(
            ValidTwoFactorAuthenticationCodeProvided::class,
            TrustedDeviceRemember::class,
        );

        Event::listen(
            TwoFactorAuthenticationDisabled::class,
            TrustedDeviceInvalidate::class,
        );
    }
}
