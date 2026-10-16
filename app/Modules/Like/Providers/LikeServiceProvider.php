<?php

declare(strict_types=1);

namespace App\Modules\Like\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class LikeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->register(LikeRouteServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
