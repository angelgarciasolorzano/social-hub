<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Providers;

use App\Auth\Modules\TrustedDevice\Events\TrustedDeviceEventRecorded;
use App\Modules\ActivityLog\Console\Commands\ActivityLogPurgeCommand;
use App\Modules\ActivityLog\Listeners\AccountActivitySubscriber;
use App\Modules\ActivityLog\Listeners\AuthActivitySubscriber;
use App\Modules\ActivityLog\Listeners\ContentActivitySubscriber;
use App\Modules\ActivityLog\Listeners\LikeActivitySubscriber;
use App\Modules\ActivityLog\Listeners\TrustedDeviceActivityMirror;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
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
        $this->registerListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([ActivityLogPurgeCommand::class]);
        }

        $this->registerPurgeSchedule();
    }

    /**
     * Listen to the domain events whose actions are recorded in the activity log.
     */
    private function registerListeners(): void
    {
        Event::subscribe(AuthActivitySubscriber::class);
        Event::subscribe(ContentActivitySubscriber::class);
        Event::subscribe(LikeActivitySubscriber::class);
        Event::subscribe(AccountActivitySubscriber::class);
        Event::listen(TrustedDeviceEventRecorded::class, TrustedDeviceActivityMirror::class);
    }

    /**
     * Schedule the daily purge of activity past each log's retention. Runs at 03:30
     * server local time, after `trusted-devices:purge`.
     */
    private function registerPurgeSchedule(): void
    {
        Schedule::command('activity-log:purge')
            ->dailyAt('03:30');
    }
}
