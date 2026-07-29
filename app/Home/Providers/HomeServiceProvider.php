<?php

declare(strict_types=1);

namespace App\Home\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class HomeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->register(HomeRouteServiceProvider::class);
    }
}
