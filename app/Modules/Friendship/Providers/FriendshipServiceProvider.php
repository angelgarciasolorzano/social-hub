<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Providers;

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
        $this->mergeConfigFrom(
            __DIR__.'/../config/friendship.php',
            'module.friendship',
        );

        $this->app->register(FriendshipRouteServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
