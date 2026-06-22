<?php

declare(strict_types=1);

namespace App\Friendship\Providers;

use Illuminate\Support\ServiceProvider;

class FriendshipServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->register(FriendshipRouteServiceProvider::class);
    }
}
