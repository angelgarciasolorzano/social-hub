<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->register(NotificationRouteServiceProvider::class);
    }
}
