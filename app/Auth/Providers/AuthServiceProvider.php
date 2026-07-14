<?php

declare(strict_types=1);

namespace App\Auth\Providers;

use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->app->register(AuthRouteServiceProvider::class);
        $this->app->register(AuthEventServiceProvider::class);
    }
}
