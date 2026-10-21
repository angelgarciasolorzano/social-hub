<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class ActivityLogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/activity-log.php',
            'module.activity_log',
        );
    }
}
