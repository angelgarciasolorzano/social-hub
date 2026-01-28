<?php

namespace App\User\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

class UserRouteServiceProvider extends RouteServiceProvider
{
    /**
     * Bootstrap any application services.
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware('web')
                ->group(__DIR__ . '/../routes/routes.php');
        });
    }
}