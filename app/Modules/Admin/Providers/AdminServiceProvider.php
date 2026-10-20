<?php

declare(strict_types=1);

namespace App\Modules\Admin\Providers;

use App\Modules\Admin\Console\Commands\AdminBootstrapCommand;
use App\Modules\Admin\Console\Commands\AdminRolePermissionsCommand;
use App\Modules\Admin\Console\Commands\AdminUserRoleCommand;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/invitations.php');

        $this->loadViewsFrom(__DIR__.'/../UI/Views', 'admin');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AdminBootstrapCommand::class,
                AdminRolePermissionsCommand::class,
                AdminUserRoleCommand::class,
            ]);
        }
    }
}
