<?php

declare(strict_types=1);

namespace App\Home\Providers;

use Illuminate\Support\ServiceProvider;

class HomeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->register(HomeRouteServiceProvider::class);
    }
}
