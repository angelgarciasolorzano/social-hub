<?php

declare(strict_types=1);

namespace App\Friendship\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class FriendshipServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->register(FriendshipRouteServiceProvider::class);
    }
}
