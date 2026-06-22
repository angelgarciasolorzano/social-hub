<?php

declare(strict_types=1);

namespace App\User\Providers;

use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->register(UserRouteServiceProvider::class);
    }
}
