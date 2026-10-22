<?php

declare(strict_types=1);

namespace App\Modules\Admin\Jobs;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

final class RunDatabaseBackup implements ShouldQueue
{
    use Queueable;

    private const string BACKUP_DISK = 'backups';

    public readonly string $filename;

    public function __construct(public readonly ?int $actorId = null)
    {
        $this->filename = 'database-backup-'.Str::ulid().'.zip';
        $this->chain([new RunBackupCleanup]);
    }

    public function handle(ActivityLogger $activityLogger): void
    {
        $this->assertArchivePasswordIsConfigured();

        $causer = $this->actorId === null
            ? null
            : User::query()->findOrFail($this->actorId);

        $exitCode = Artisan::call('backup:run', [
            '--only-db' => true,
            '--only-to-disk' => self::BACKUP_DISK,
            '--filename' => $this->filename,
        ]);

        throw_if($exitCode !== Command::SUCCESS, RuntimeException::class, 'The database backup command failed.');

        $activityLogger->record(
            ActivityLogAdminEvent::BackupCreated,
            causer: $causer,
            properties: [
                'filename' => $this->filename,
                'origin' => $this->actorId === null ? 'system' : 'manual',
            ],
        );
    }

    private function assertArchivePasswordIsConfigured(): void
    {
        $archivePassword = config('backup.backup.password');

        throw_unless(
            \is_string($archivePassword) && trim($archivePassword) !== '',
            RuntimeException::class,
            'A non-empty database backup passphrase is required.',
        );
    }
}
