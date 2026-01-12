<?php

namespace App\User\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;

class UserServiceProvider extends RouteServiceProvider
{
    /**
     * Bootstrap any application services.
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->app->register(UserRouteServiceProvider::class);
    }
}