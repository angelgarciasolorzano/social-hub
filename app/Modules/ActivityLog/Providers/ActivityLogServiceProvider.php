<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Providers;

use App\Auth\Modules\TrustedDevice\Events\TrustedDeviceEventRecorded;
use App\Modules\ActivityLog\Listeners\AuthActivitySubscriber;
use App\Modules\ActivityLog\Listeners\ContentActivitySubscriber;
use App\Modules\ActivityLog\Listeners\LikeActivitySubscriber;
use App\Modules\ActivityLog\Listeners\TrustedDeviceActivityMirror;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Override;

class ActivityLogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/activity-log.php',
            'module.activity_log',
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::subscribe(AuthActivitySubscriber::class);
        Event::subscribe(ContentActivitySubscriber::class);
        Event::subscribe(LikeActivitySubscriber::class);
        Event::listen(TrustedDeviceEventRecorded::class, TrustedDeviceActivityMirror::class);
    }
}
