<?php

declare(strict_types=1);

namespace App\Friendship\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;
use Override;

class FriendshipRouteServiceProvider extends RouteServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    #[Override]
    public function boot(): void
    {
        $this->routes(function (): void {
            Route::middleware('web')
                ->group(__DIR__.'/../routes/routes.php');
        });
    }
}
