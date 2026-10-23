<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** @return Testable<BackupManagement> */
function backupManagementPageForTesting(): Testable
{
    return Livewire::test(BackupManagement::class);
}

/** Grant one backup operation to a test admin using the canonical admin guard. */
function grantPermissionForBackupPage(User $user, AdminPermission $adminPermission): void
{
    $permissionRecord = Permission::query()
        ->where('name', $adminPermission->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();

    $role = Role::query()->create([
        'name' => 'backup-page-'.$adminPermission->name,
        'guard_name' => 'admin',
    ]);

    $role->givePermissionTo($permissionRecord);

    $user->assignRole($role);
}

/** Store an archive in the configured test destination and set its timestamp. */
function storeFilamentBackupArchive(string $filename, string $contents): void
{
    $path = config('backup.backup.name').'/'.$filename;

    Storage::disk('backups')->put($path, $contents);
    touch(Storage::disk('backups')->path($path), now()->getTimestamp());
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);

    config()->set('backup.backup.name', 'social-hub-test');
    config()->set('backup.monitor_backups.0.name', 'social-hub-test');
});

it('renders the page and navigation to an authorized backup reader', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    $user->saveAppAuthenticationSecret('filament-test-secret');

    config()->set('filesystems.disks.backups.bucket', 'social-hub-backups');
    config()->set('filesystems.disks.backups.region', 'us-west-2');
    config()->set('filesystems.disks.backups.endpoint', 'http://localhost:4566');
    config()->set('filesystems.disks.backups.secret', 'backup-secret-must-not-render');
    config()->set('backup.backup.password', 'archive-password-must-not-render');
    Storage::fake('backups');
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

    $filename = (new RunDatabaseBackup)->filename;
    storeFilamentBackupArchive($filename, 'backup archive');

    $this->get(BackupManagement::getUrl(isAbsolute: false))
        ->assertOk()
        ->assertSeeText('Backups')
        ->assertSeeText('Backup destination')
        ->assertSeeText('Healthy')
        ->assertSeeText('S3-compatible storage')
        ->assertSeeText('social-hub-backups')
        ->assertSeeText('social-hub-test')
        ->assertSeeText('us-west-2')
        ->assertSeeText('Private')
        ->assertSeeText('AES-256')
        ->assertSeeText('Retention policy')
        ->assertSeeText('7 days')
        ->assertSeeText('4 weeks')
        ->assertSeeText('6 months')
        ->assertDontSee('http://localhost:4566')
        ->assertDontSee('backup-secret-must-not-render')
        ->assertDontSee('archive-password-must-not-render')
        ->assertSeeText($filename)
        ->assertSeeText('14 bytes')
        ->assertSeeText('backups')
        ->assertSeeText('Available');
});

it('forbids an administrator without backup view permission from opening the page URL', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin);
    $user->saveAppAuthenticationSecret('filament-test-secret');

    $this->get(BackupManagement::getUrl(isAbsolute: false))
        ->assertForbidden();
});

it('queues a backup from the authorized page action', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    grantPermissionForBackupPage($user, AdminPermission::AdminBackupsCreate);
    Queue::fake([RunDatabaseBackup::class]);

    backupManagementPageForTesting()
        ->call('mountAction', 'createBackup')
        ->call('callMountedAction');

    Queue::assertPushed(
        RunDatabaseBackup::class,
        fn (RunDatabaseBackup $runDatabaseBackup): bool => $runDatabaseBackup->actorId === $user->id,
    );
});

it('rejects a direct backup creation call without the create permission', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    Queue::fake([RunDatabaseBackup::class]);

    backupManagementPageForTesting()
        ->call('createBackup')
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('requires the current password before a backup can be downloaded', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    grantPermissionForBackupPage($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    storeFilamentBackupArchive($filename, 'backup archive');

    backupManagementPageForTesting()
        ->call('mountTableAction', 'downloadBackup', $filename)
        ->call('callMountedTableAction')
        ->assertHasErrors(['mountedActions.0.data.current_password' => 'required']);
});

it('creates a temporary download redirect for an authorized request with the current password', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    grantPermissionForBackupPage($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    storeFilamentBackupArchive($filename, 'backup archive');
    $temporaryUrl = 'https://backup-storage.example.test/presigned-download';

    /** @var FilesystemAdapter $filesystem */
    $filesystem = Storage::disk('backups');
    $filesystem->buildTemporaryUrlsUsing(
        fn (string $requestedPath, CarbonImmutable $expiration, array $temporaryUrlOptions): string => $temporaryUrl,
    );

    backupManagementPageForTesting()
        ->call('mountTableAction', 'downloadBackup', $filename)
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedTableAction')
        ->assertRedirect($temporaryUrl);
});

it('rejects direct backup downloads without download permission', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    Storage::fake('backups');

    backupManagementPageForTesting()
        ->call('downloadBackup', 'database-backup-01J00000000000000000000000.zip', 'password')
        ->assertForbidden();
});

it('exposes only download actions for backup rows without delete or restore actions', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    grantPermissionForBackupPage($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    storeFilamentBackupArchive($filename, 'backup archive');

    backupManagementPageForTesting()
        ->assertSeeText('Download')
        ->assertDontSeeText('Delete')
        ->assertDontSeeText('Restore');
});
