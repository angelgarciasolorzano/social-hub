<?php

declare(strict_types=1);

namespace App\Post\Providers;

use Illuminate\Support\ServiceProvider;

class PostServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(PostRouteServiceProvider::class);
    }
}
