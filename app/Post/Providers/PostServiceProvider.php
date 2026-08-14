<?php

declare(strict_types=1);

namespace App\Post\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class PostServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->register(PostRouteServiceProvider::class);
    }
}
