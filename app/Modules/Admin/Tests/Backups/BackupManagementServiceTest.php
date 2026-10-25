<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use App\Modules\Admin\Services\BackupManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

beforeEach(function (): void {
    config()->set('backup.backup.name', 'social-hub-test');
    config()->set('backup.monitor_backups.0.name', 'social-hub-test');
});

it('forbids listing backups without the view permission', function (): void {
    $user = createUser();
    Storage::fake('backups');

    expect(fn () => resolve(BackupManagementService::class)->listBackups($user))
        ->toThrow(AuthorizationException::class);
});

it('lists available backup metadata newest first and reports healthy checks', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminBackupsReader->value);
    Storage::fake('backups');
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

    $olderFilename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($olderFilename, 'older', now()->subDays(2));

    $newerFilename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($newerFilename, 'newest', now());

    $backupListingData = resolve(BackupManagementService::class)->listBackups($user);

    expect(array_column($backupListingData->backups, 'filename'))
        ->toBe([$newerFilename, $olderFilename])
        ->and($backupListingData->backups[0])
        ->toMatchArray([
            'filename' => $newerFilename,
            'size_in_bytes' => 6.0,
            'disk' => 'backups',
            'available' => true,
        ])
        ->and($backupListingData->health)
        ->toBe([
            'healthy' => true,
            'failed_checks' => [],
        ]);
});

it('reports the configured maximum backup age check as unhealthy', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminBackupsReader->value);
    Storage::fake('backups');
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

    $filename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($filename, 'old archive', now()->subDays(2));

    $backupListingData = resolve(BackupManagementService::class)->listBackups($user);

    expect($backupListingData->health['healthy'])->toBeFalse()
        ->and($backupListingData->health['failed_checks'])->toContain('MaximumAgeInDays');
});

it('reports the configured maximum storage check as unhealthy', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    config()->set('backup.monitor_backups.0.health_checks', [
        MaximumAgeInDays::class => 1,
        MaximumStorageInMegabytes::class => 0,
    ]);

    $user = createUser();
    $user->assignRole(AdminRole::AdminBackupsReader->value);
    Storage::fake('backups');
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

    storeBackupArchive((new RunDatabaseBackup)->filename, 'non-empty archive');

    $backupListingData = resolve(BackupManagementService::class)->listBackups($user);

    expect($backupListingData->health['healthy'])->toBeFalse()
        ->and($backupListingData->health['failed_checks'])->toContain('MaximumStorageInMegabytes');
});

it('forbids queueing a backup without the create permission', function (): void {
    $user = createUser();
    Queue::fake([RunDatabaseBackup::class]);

    expect(fn () => resolve(BackupManagementService::class)->queueBackup($user))
        ->toThrow(AuthorizationException::class);

    Queue::assertNothingPushed();
});

it('queues a backup with the requesting admin as actor', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsCreate);
    Queue::fake([RunDatabaseBackup::class]);

    resolve(BackupManagementService::class)->queueBackup($user);

    Queue::assertPushed(
        RunDatabaseBackup::class,
        fn (RunDatabaseBackup $runDatabaseBackup): bool => $runDatabaseBackup->actorId === $user->id,
    );
});

it('forbids downloading without the download permission', function (): void {
    $user = createUser();

    expect(fn () => resolve(BackupManagementService::class)->downloadBackup(
        $user,
        'database-backup-01J00000000000000000000000.zip',
        'password',
    ))->toThrow(AuthorizationException::class)
        ->and(Activity::query()
            ->exists())
        ->toBeFalse();
});

it('generates a five-minute URL and audits only the filename for a valid download', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

    $filename = (new RunDatabaseBackup)->filename;
    $path = storeBackupArchive($filename, 'backup archive');

    /** @var array{path: string, expiration_timestamp: int}|null $temporaryUrlRequest */
    $temporaryUrlRequest = null;

    /** @var FilesystemAdapter $filesystem */
    $filesystem = Storage::disk('backups');

    $filesystem->buildTemporaryUrlsUsing(
        function (string $requestedPath, CarbonImmutable $expiration, array $temporaryUrlOptions) use (&$temporaryUrlRequest): string {
            $temporaryUrlRequest = [
                'path' => $requestedPath,
                'expiration_timestamp' => $expiration->getTimestamp(),
            ];

            return 'https://backup-storage.example.test/presigned-download';
        },
    );

    $downloadUrl = resolve(BackupManagementService::class)->downloadBackup($user, $filename, 'password');

    expect($downloadUrl)->toBe('https://backup-storage.example.test/presigned-download')
        ->and($temporaryUrlRequest['path'] ?? null)
        ->toBe($path)
        ->and(($temporaryUrlRequest['expiration_timestamp'] ?? 0) - now()->getTimestamp())
        ->toBe(300);

    $activity = Activity::query()->sole();

    expect($activity)
        ->event->toBe(ActivityLogAdminEvent::BackupDownloaded->value)
        ->causer_id->toBe($user->id)
        ->and($activity->properties?->all())->toBe(['filename' => $filename]);
});

it('does not create a URL or audit a download with an incorrect password', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($filename, 'backup archive');
    $temporaryUrlRequested = false;

    /** @var FilesystemAdapter $filesystem */
    $filesystem = Storage::disk('backups');

    $filesystem->buildTemporaryUrlsUsing(
        function (string $requestedPath, CarbonImmutable $expiration, array $temporaryUrlOptions) use (&$temporaryUrlRequested): string {
            $temporaryUrlRequested = true;

            return 'https://backup-storage.example.test/presigned-download';
        },
    );

    expect(fn () => resolve(BackupManagementService::class)->downloadBackup($user, $filename, 'wrong-password'))
        ->toThrow(ValidationException::class)
        ->and($temporaryUrlRequested)
        ->toBeFalse()
        ->and(Activity::query()
            ->exists())
        ->toBeFalse();
});

it('rejects backup paths outside the configured destination without creating a URL or audit', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    Storage::disk('backups')->put('outside/'.$filename, 'backup archive');
    $temporaryUrlRequested = false;

    /** @var FilesystemAdapter $filesystem */
    $filesystem = Storage::disk('backups');

    $filesystem->buildTemporaryUrlsUsing(
        function (string $requestedPath, CarbonImmutable $expiration, array $temporaryUrlOptions) use (&$temporaryUrlRequested): string {
            $temporaryUrlRequested = true;

            return 'https://backup-storage.example.test/presigned-download';
        },
    );

    expect(fn () => resolve(BackupManagementService::class)->downloadBackup($user, 'outside/'.$filename, 'password'))
        ->toThrow(ValidationException::class)
        ->and($temporaryUrlRequested)
        ->toBeFalse()
        ->and(Activity::query()
            ->exists())
        ->toBeFalse();
});

it('rejects a missing backup object without creating a URL or audit', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');
    $temporaryUrlRequested = false;

    /** @var FilesystemAdapter $filesystem */
    $filesystem = Storage::disk('backups');

    $filesystem->buildTemporaryUrlsUsing(
        function (string $requestedPath, CarbonImmutable $expiration, array $temporaryUrlOptions) use (&$temporaryUrlRequested): string {
            $temporaryUrlRequested = true;

            return 'https://backup-storage.example.test/presigned-download';
        },
    );

    expect(fn () => resolve(BackupManagementService::class)->downloadBackup(
        $user,
        (new RunDatabaseBackup)->filename,
        'password',
    ))->toThrow(ValidationException::class)
        ->and($temporaryUrlRequested)
        ->toBeFalse()
        ->and(Activity::query()
            ->exists())
        ->toBeFalse();
});
