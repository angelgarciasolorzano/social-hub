<?php

namespace App\Friendship\Providers;

use Illuminate\Support\ServiceProvider;

class FriendshipServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->app->register(FriendshipRouteServiceProvider::class);
    }
}