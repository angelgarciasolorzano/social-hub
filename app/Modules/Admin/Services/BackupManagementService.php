<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Admin\Data\BackupListingData;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use App\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\BackupDestination\BackupDestinationFactory;
use Spatie\Backup\Config\Config as SpatieBackupConfig;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;

final readonly class BackupManagementService
{
    private const string BACKUP_DISK = 'backups';

    private const int DOWNLOAD_URL_EXPIRATION_MINUTES = 5;

    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * List configured backups and report destination health for the authorized user.
     *
     * @return array{
     *     backups: list<array{
     *         filename: string,
     *         created_at: string|null,
     *         size_in_bytes: float|null,
     *         disk: string,
     *         available: bool
     *     }>,
     *     health: array{healthy: bool, failed_checks: list<string>}
     * }
     */
    public function listBackups(User $user): array
    {
        $this->authorize($user, AdminPermission::AdminBackupsView);

        $backupConfig = $this->backupConfig();
        $backupDestination = $this->backupDestination($backupConfig);
        $backupName = $backupConfig->backup->name;

        $backups = [];

        foreach ($backupDestination->backups() as $backup) {
            if (! $this->isRootArchive($backup, $backupName)) {
                continue;
            }

            $available = $backup->disk()->exists($backup->path());

            $backups[] = [
                'filename' => basename($backup->path()),
                'created_at' => $available ? $backup->date()->toIso8601String() : null,
                'size_in_bytes' => $available ? (float) $backup->disk()->size($backup->path()) : null,
                'disk' => $backupDestination->diskName(),
                'available' => $available,
            ];
        }

        return [
            'backups' => $backups,
            'health' => $this->backupHealth($backupConfig),
        ];
    }

    /** Return the authorized listing as a typed Data object for Filament consumers. */
    public function listBackupsData(User $user): BackupListingData
    {
        $backupListing = $this->listBackups($user);

        return new BackupListingData(
            backups: $backupListing['backups'],
            health: $backupListing['health'],
        );
    }

    /** Queue a database backup for an admin with permission to create backups. */
    public function queueBackup(User $user): void
    {
        $this->authorize($user, AdminPermission::AdminBackupsCreate);

        dispatch(new RunDatabaseBackup($user->id));
    }

    /** Create a short-lived download URL after authorization and password confirmation. */
    public function downloadBackup(User $user, string $filename, string $currentPassword): string
    {
        $this->authorize($user, AdminPermission::AdminBackupsDownload);
        $this->assertCurrentPasswordIsValid($user, $currentPassword);

        $backupConfig = $this->backupConfig();
        $backupDestination = $this->backupDestination($backupConfig);
        $backup = $this->findBackup($backupDestination, $filename, $backupConfig->backup->name);

        if (! $backup instanceof Backup) {
            throw ValidationException::withMessages([
                'filename' => __('The selected backup is no longer available.'),
            ]);
        }

        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($backupDestination->diskName());

        $temporaryUrl = $filesystem->temporaryUrl(
            $backup->path(),
            now()->addMinutes(self::DOWNLOAD_URL_EXPIRATION_MINUTES),
        );

        $this->activityLogger->record(
            ActivityLogAdminEvent::BackupDownloaded,
            causer: $user,
            properties: ['filename' => $filename],
        );

        return $temporaryUrl;
    }

    /** Ensure the user has the requested backup permission. */
    private function authorize(User $user, AdminPermission $adminPermission): void
    {
        throw_unless($user->can($adminPermission->value), AuthorizationException::class);
    }

    /** Reject downloads when the supplied password does not match the current password. */
    private function assertCurrentPasswordIsValid(User $user, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The password is incorrect.'),
            ]);
        }
    }

    /** Load the package configuration used by backup operations. */
    private function backupConfig(): SpatieBackupConfig
    {
        return SpatieBackupConfig::fromArray(config('backup'));
    }

    /** Resolve the dedicated backup destination or fail clearly. */
    private function backupDestination(SpatieBackupConfig $spatieBackupConfig): BackupDestination
    {
        $backupDestination = BackupDestinationFactory::createFromArray($spatieBackupConfig)
            ->first(fn (BackupDestination $backupDestination): bool => $backupDestination->diskName() === self::BACKUP_DISK);

        throw_unless($backupDestination instanceof BackupDestination, RuntimeException::class, 'The dedicated backup disk is not configured as a backup destination.');

        return $backupDestination;
    }

    /** Find an available root archive matching the requested filename. */
    private function findBackup(BackupDestination $backupDestination, string $filename, string $backupName): ?Backup
    {
        if (
            $filename === ''
            || basename($filename) !== $filename
            || str_contains($filename, '\\')
            || ! str_ends_with($filename, '.zip')
        ) {
            return null;
        }

        $backupPath = $backupName.'/'.$filename;
        $backup = $backupDestination->backups()
            ->first(fn (Backup $backup): bool => $backup->path() === $backupPath);

        if (! $backup instanceof Backup || ! $backup->disk()->exists($backup->path())) {
            return null;
        }

        return $backup;
    }

    /** Determine whether a backup is a ZIP archive at the destination root. */
    private function isRootArchive(Backup $backup, string $backupName): bool
    {
        $backupPath = $backup->path();
        $pathPrefix = $backupName.'/';

        return str_starts_with($backupPath, $pathPrefix)
            && ! str_contains(substr($backupPath, \strlen($pathPrefix)), '/')
            && str_ends_with($backupPath, '.zip');
    }

    /**
     * Summarize the monitored backup destination's health checks.
     *
     * @return array{healthy: bool, failed_checks: list<string>}
     */
    private function backupHealth(SpatieBackupConfig $spatieBackupConfig): array
    {
        $backupDestinationStatus = BackupDestinationStatusFactory::createForMonitorConfig($spatieBackupConfig->monitoredBackups)
            ->first(function (BackupDestinationStatus $backupDestinationStatus) use ($spatieBackupConfig): bool {
                $backupDestination = $backupDestinationStatus->backupDestination();

                return $backupDestination->backupName() === $spatieBackupConfig->backup->name
                    && $backupDestination->diskName() === self::BACKUP_DISK;
            });

        if (! $backupDestinationStatus instanceof BackupDestinationStatus) {
            return [
                'healthy' => false,
                'failed_checks' => ['MonitorConfigurationMissing'],
            ];
        }

        $healthy = $backupDestinationStatus->isHealthy();
        $failedChecks = [];

        foreach ($backupDestinationStatus->getHealthCheckFailures() as $healthCheckFailure) {
            $failedChecks[] = class_basename($healthCheckFailure->healthCheck());
        }

        return [
            'healthy' => $healthy,
            'failed_checks' => $failedChecks,
        ];
    }
}
