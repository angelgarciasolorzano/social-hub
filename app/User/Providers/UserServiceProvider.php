<?php

declare(strict_types=1);

namespace App\User\Providers;

use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(UserRouteServiceProvider::class);
    }
}
