<?php

declare(strict_types=1);

namespace App\Modules\Like\Providers;

use Illuminate\Support\ServiceProvider;

class LikeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
