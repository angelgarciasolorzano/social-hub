<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class AnnouncementServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->register(AnnouncementRouteServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
