<?php

declare(strict_types=1);

use App\Modules\Admin\Jobs\RunBackupCleanup;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

it('schedules daily backups and an independent health monitor without overlapping events', function (): void {
    $scheduledTasks = collect(Schedule::events())
        ->filter(fn (Event $event): bool => $event->description === RunDatabaseBackup::class
            || $event->description === RunBackupCleanup::class
            || str_contains($event->command ?? '', 'backup:clean')
            || str_contains($event->command ?? '', 'backup:monitor'))
        ->map(fn (Event $event): array => [
            'task' => match (true) {
                $event->description === RunDatabaseBackup::class => RunDatabaseBackup::class,
                $event->description === RunBackupCleanup::class => RunBackupCleanup::class,
                str_contains($event->command ?? '', 'backup:clean') => 'backup:clean',
                default => 'backup:monitor',
            },
            'expression' => $event->getExpression(),
            'without_overlapping' => $event->withoutOverlapping,
        ])
        ->values()
        ->all();

    expect($scheduledTasks)->toBe([
        [
            'task' => RunDatabaseBackup::class,
            'expression' => '0 1 * * *',
            'without_overlapping' => true,
        ],
        [
            'task' => 'backup:monitor',
            'expression' => '30 4 * * *',
            'without_overlapping' => true,
        ],
    ]);
});

it('emails unhealthy backup alerts for age and storage check failures', function (): void {
    $monitoredBackupConfiguration = config('backup.monitor_backups.0');
    $healthChecks = is_array($monitoredBackupConfiguration)
        ? ($monitoredBackupConfiguration['health_checks'] ?? null)
        : null;
    $notificationChannels = config('backup.notifications.notifications');

    expect($notificationChannels[UnhealthyBackupWasFoundNotification::class])
        ->toContain('mail')
        ->and($healthChecks)
        ->toBe([
            MaximumAgeInDays::class => 1,
            MaximumStorageInMegabytes::class => 5000,
        ])
        ->and(config('backup.notifications.mail.to'))
        ->not->toBeEmpty();
});
