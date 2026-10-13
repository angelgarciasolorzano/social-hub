<?php

declare(strict_types=1);

namespace App\Modules\Comment\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class CommentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->register(CommentRouteServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
