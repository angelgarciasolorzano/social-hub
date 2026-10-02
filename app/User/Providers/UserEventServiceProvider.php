<?php

declare(strict_types=1);

namespace App\User\Providers;

use App\User\Modules\TwoFactor\Listeners\TrackRecoveryCodesRegeneration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\RecoveryCodesGenerated;

class UserEventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            RecoveryCodesGenerated::class,
            TrackRecoveryCodesRegeneration::class,
        );
    }
}
