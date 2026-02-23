<?php

namespace App\MediaLibrary\Providers;

use App\MediaLibrary\Console\Commands\CleanMediaLibraryFolders;
use Illuminate\Support\ServiceProvider;

class MediaLibraryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([
            CleanMediaLibraryFolders::class,
        ]);
    }
}
