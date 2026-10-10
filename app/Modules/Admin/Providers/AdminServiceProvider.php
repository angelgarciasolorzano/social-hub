<?php

declare(strict_types=1);

namespace App\Modules\Admin\Providers;

use App\Modules\Admin\Console\Commands\AdminBootstrapCommand;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([AdminBootstrapCommand::class]);
        }
    }
}
