<?php

declare(strict_types=1);

namespace App\Friendship\Providers;

use Illuminate\Support\ServiceProvider;

class FriendshipServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(FriendshipRouteServiceProvider::class);
    }
}
