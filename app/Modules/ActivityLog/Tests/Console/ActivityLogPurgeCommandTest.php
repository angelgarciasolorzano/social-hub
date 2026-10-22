<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogLikeEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

const ACTIVITY_LOG_PURGE_NOW = '2026-10-21 12:00:00';

/**
 * Record the event as if it happened the given number of days before the purge runs.
 */
function recordActivityDaysAgo(TestCase $testCase, ActivityLogEvent $activityLogEvent, int $daysAgo): Activity
{
    $testCase->travelTo(CarbonImmutable::parse(ACTIVITY_LOG_PURGE_NOW)->subDays($daysAgo));

    resolve(ActivityLogger::class)->record($activityLogEvent);

    $testCase->travelTo(CarbonImmutable::parse(ACTIVITY_LOG_PURGE_NOW));

    return Activity::query()->latest('id')->firstOrFail();
}

it('deletes each log past its own retention and keeps the rest', function (): void {
    config()->set('module.activity_log.retention_days.likes', 30);
    config()->set('module.activity_log.retention_days.admin', 730);

    $activity = recordActivityDaysAgo($this, ActivityLogLikeEvent::Added, 31);
    $recentLike = recordActivityDaysAgo($this, ActivityLogLikeEvent::Added, 10);
    $oldAdminAudit = recordActivityDaysAgo($this, ActivityLogAdminEvent::RoleGranted, 31);
    $expiredAdminAudit = recordActivityDaysAgo($this, ActivityLogAdminEvent::RoleGranted, 731);

    $exitCode = Artisan::call('activity-log:purge');

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('Purged 1 likes activities older than 30 days.')
        ->toContain('Purged 1 admin activities older than 730 days.');

    $this->assertModelMissing($activity);
    $this->assertModelMissing($expiredAdminAudit);
    $this->assertModelExists($recentLike);
    $this->assertModelExists($oldAdminAudit);
});

it('follows a changed retention for only that log', function (): void {
    config()->set('module.activity_log.retention_days.likes', 5);
    config()->set('module.activity_log.retention_days.admin', 730);

    $activity = recordActivityDaysAgo($this, ActivityLogLikeEvent::Added, 6);
    $adminAudit = recordActivityDaysAgo($this, ActivityLogAdminEvent::RoleGranted, 6);

    Artisan::call('activity-log:purge');

    $this->assertModelMissing($activity);
    $this->assertModelExists($adminAudit);
});

it('rejects a non-positive retention without deleting anything', function (): void {
    config()->set('module.activity_log.retention_days.likes', 0);

    $activity = recordActivityDaysAgo($this, ActivityLogLikeEvent::Added, 400);
    $adminAudit = recordActivityDaysAgo($this, ActivityLogAdminEvent::RoleGranted, 800);

    $exitCode = Artisan::call('activity-log:purge');

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('The retention of the likes log must be a positive integer, got 0.');

    $this->assertModelExists($activity);
    $this->assertModelExists($adminAudit);
});

it('schedules the purge daily at 03:30', function (): void {
    $purgeEvents = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'activity-log:purge'));

    expect($purgeEvents)->toHaveCount(1)
        ->and($purgeEvents->first()?->expression)->toBe('30 3 * * *');
});
