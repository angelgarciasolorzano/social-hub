<?php

declare(strict_types=1);

namespace App\User\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->register(UserRouteServiceProvider::class);
    }
}
