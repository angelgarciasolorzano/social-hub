<?php

declare(strict_types=1);

namespace App\Providers;

use App\Comment\Models\Comment;
use App\Like\Models\Like;
use App\Post\Models\Post;
use App\User\Models\User;
use DeviceDetector\DeviceDetector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(DeviceDetector::class, fn (Application $application): DeviceDetector => $this->buildDeviceDetector($application));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            User::MORPH_NAME => User::class,
            Post::MORPH_NAME => Post::class,
            Like::MORPH_NAME => Like::class,
            Comment::MORPH_NAME => Comment::class,
        ]);
    }

    /**
     * Build and configure an instance of DeviceDetector.
     */
    private function buildDeviceDetector(Application $application): DeviceDetector
    {
        $request = $application->make(Request::class);

        $deviceDetector = new DeviceDetector($request->userAgent() ?? '');
        $deviceDetector->parse();

        return $deviceDetector;
    }
}
