<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Admin\Jobs\RunBackupCleanup;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use App\Modules\Admin\Services\DashboardBackupSummaryCache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Console\Command\Command;

function expectDatabaseBackupCommandToReturn(RunDatabaseBackup $runDatabaseBackup, int $exitCode): void
{
    Artisan::expects('call')
        ->once()
        ->with('backup:run', [
            '--only-db' => true,
            '--only-to-disk' => 'backups',
            '--filename' => $runDatabaseBackup->filename,
        ])
        ->andReturn($exitCode);
}

beforeEach(function (): void {
    config()->set('backup.backup.password', 'test-backup-passphrase');
});

it('queues the backup with its actor and cleanup chained after success', function (): void {
    Queue::fake();
    Artisan::expects('call')->never();

    dispatch(new RunDatabaseBackup(42));

    Queue::assertPushed(
        RunDatabaseBackup::class,
        function (RunDatabaseBackup $runDatabaseBackup): bool {
            $runDatabaseBackup->assertHasChain([new RunBackupCleanup]);

            return $runDatabaseBackup->actorId === 42;
        },
    );
});

it('runs a database-only backup on the private S3 disk and audits the actor', function (): void {
    $user = createUser();
    $job = new RunDatabaseBackup($user->id);
    expectDatabaseBackupCommandToReturn($job, Command::SUCCESS);

    $job->handle(resolve(ActivityLogger::class), resolve(DashboardBackupSummaryCache::class));

    $activity = Activity::query()->sole();

    expect($activity)
        ->log_name->toBe('admin')
        ->event->toBe(ActivityLogAdminEvent::BackupCreated->value)
        ->causer_id->toBe($user->id)
        ->and($activity->properties?->all())->toEqualCanonicalizing([
            'filename' => $job->filename,
            'origin' => 'manual',
        ]);
});

it('records a scheduled backup as a system action without a causer', function (): void {
    $job = new RunDatabaseBackup;
    expectDatabaseBackupCommandToReturn($job, Command::SUCCESS);

    $job->handle(resolve(ActivityLogger::class), resolve(DashboardBackupSummaryCache::class));

    $activity = Activity::query()->sole();

    expect($activity)
        ->log_name->toBe('admin')
        ->event->toBe(ActivityLogAdminEvent::BackupCreated->value)
        ->causer_type->toBeNull()
        ->causer_id->toBeNull()
        ->and($activity->properties?->all())->toEqualCanonicalizing([
            'filename' => $job->filename,
            'origin' => 'system',
        ]);
});

it('invalidates the dashboard backup summary when the backup command finishes', function (): void {
    $job = new RunDatabaseBackup;
    Cache::put(DashboardBackupSummaryCache::KEY, 'stale summary');
    expectDatabaseBackupCommandToReturn($job, Command::SUCCESS);

    $job->handle(resolve(ActivityLogger::class), resolve(DashboardBackupSummaryCache::class));

    expect(Cache::has(DashboardBackupSummaryCache::KEY))->toBeFalse();
});

it('does not audit a backup when the command fails', function (): void {
    $user = createUser();
    $job = new RunDatabaseBackup($user->id);
    expectDatabaseBackupCommandToReturn($job, Command::FAILURE);
    Cache::put(DashboardBackupSummaryCache::KEY, 'stale summary');

    expect(fn () => $job->handle(resolve(ActivityLogger::class), resolve(DashboardBackupSummaryCache::class)))
        ->toThrow(RuntimeException::class, 'The database backup command failed.')
        ->and(Activity::query()
            ->exists())
        ->toBeFalse()
        ->and(Cache::has(DashboardBackupSummaryCache::KEY))
        ->toBeFalse();
});

it('fails closed without running or auditing when the backup passphrase is missing', function (?string $archivePassword): void {
    config()->set('backup.backup.password', $archivePassword);
    Artisan::expects('call')->never();

    expect(fn () => (new RunDatabaseBackup)->handle(
        resolve(ActivityLogger::class),
        resolve(DashboardBackupSummaryCache::class),
    ))
        ->toThrow(RuntimeException::class, 'A non-empty database backup passphrase is required.')
        ->and(Activity::query()
            ->exists())
        ->toBeFalse();
})->with([
    'empty passphrase' => [''],
    'whitespace passphrase' => [' '],
    'null passphrase' => [null],
]);
