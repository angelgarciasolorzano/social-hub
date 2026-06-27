<?php

declare(strict_types=1);

namespace App\Post\Providers;

use Illuminate\Support\ServiceProvider;

class PostServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->register(PostRouteServiceProvider::class);
    }
}
