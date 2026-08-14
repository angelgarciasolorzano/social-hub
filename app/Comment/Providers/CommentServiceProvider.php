<?php

declare(strict_types=1);

namespace App\Comment\Providers;

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
}
