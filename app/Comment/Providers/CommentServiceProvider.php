<?php

declare(strict_types=1);

namespace App\Comment\Providers;

use Illuminate\Support\ServiceProvider;

class CommentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(CommentRouteServiceProvider::class);
    }
}
