<?php

declare(strict_types=1);

use App\Modules\Admin\Data\BackupDestinationData;
use App\Modules\Admin\Data\BackupListingData;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use App\Modules\Admin\Filament\Pages\Backups\Widgets\BackupSummaryWidget;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use App\Modules\Admin\Services\BackupManagementService;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** @return Testable<BackupManagement> */
function backupManagementPageForTesting(): Testable
{
    return Livewire::test(BackupManagement::class)->call('loadTable');
}

/**
 * @param  list<array{filename: string, created_at: string|null, size_in_bytes: float|null, disk: string, available: bool}>  $backups
 */
function backupListingDataForSummary(array $backups, bool $healthy = true): BackupListingData
{
    return new BackupListingData(
        backups: $backups,
        health: [
            'healthy' => $healthy,
            'failed_checks' => $healthy ? [] : ['MaximumAgeInDays'],
        ],
        destination: new BackupDestinationData(
            provider: 'Test storage',
            bucket: null,
            prefix: 'social-hub-test',
            region: null,
            visibility: 'private',
            archiveEncryption: null,
            retention: [
                'keep_all_backups_for_days' => 0,
                'keep_daily_backups_for_days' => 0,
                'keep_weekly_backups_for_weeks' => 0,
                'keep_monthly_backups_for_months' => 0,
                'keep_yearly_backups_for_years' => 0,
            ],
        ),
    );
}

/**
 * @return array{
 *     backups: list<array{filename: string, created_at: string|null, size_in_bytes: float|null, disk: string, available: bool}>,
 *     health: array{healthy: bool, failed_checks: list<string>}
 * }
 */
function backupListingWidgetData(BackupListingData $backupListingData): array
{
    return [
        'backups' => $backupListingData->backups,
        'health' => $backupListingData->health,
    ];
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);

    config()->set('backup.backup.name', 'social-hub-test');
    config()->set('backup.monitor_backups.0.name', 'social-hub-test');
});

it('defers loading the backups table and displays a loading skeleton', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );

    $testable = backupManagementPageForTesting()->assertOk();
    $table = FilamentTableRecords::table($testable)->getTable();

    expect($table->isLoadingDeferred())->toBeTrue()
        ->and($table->hasLoadingSkeleton())->toBeTrue();
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
    storeBackupArchive($filename, str_repeat('a', (2 * 1024 * 1024) + (512 * 1024)));

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
        ->assertDontSeeText($filename);

    backupManagementPageForTesting()
        ->assertOk()
        ->assertSeeText($filename)
        ->assertSeeText('2.5 MB')
        ->assertDontSeeText('Disk')
        ->assertSeeText('Available');
});

it('renders backup summary counts, latest age, and total size', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    $user->saveAppAuthenticationSecret('filament-test-secret');
    Storage::fake('backups');
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

    $olderFilename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($olderFilename, str_repeat('a', 1024 * 1024), now()->subHour());
    $latestFilename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($latestFilename, str_repeat('b', (1024 * 1024) + (512 * 1024)), now()->subMinutes(30));

    $this->get(BackupManagement::getUrl(isAbsolute: false))
        ->assertOk()
        ->assertSeeText('Backup summary')
        ->assertSeeText('Available backups')
        ->assertSeeText('2')
        ->assertSeeText('Missing backups')
        ->assertSeeText('0')
        ->assertSeeText('Latest available backup')
        ->assertSeeText('30 minutes ago')
        ->assertSeeText('Oct 22, 2026 11:30')
        ->assertSeeText('Total backup size')
        ->assertSeeText('2.5 MB')
        ->assertSeeText('Destination healthy.');
});

it('passes the cached backup listing to the page widget', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    Storage::fake('backups');

    $testable = backupManagementPageForTesting();
    $backupManagement = $testable->instance();
    $backupListingData = $backupManagement->getBackupListing();

    expect($backupManagement->getWidgetData()['backupListingData'])
        ->toBe(backupListingWidgetData($backupListingData))
        ->and($backupManagement->getBackupListing())->toBe($backupListingData);
});

it('shows an explicit empty and unhealthy destination state', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    $backupListingData = backupListingDataForSummary([], healthy: false);

    Livewire::test(BackupSummaryWidget::class, ['backupListingData' => backupListingWidgetData($backupListingData)])
        ->assertOk()
        ->assertSeeText('Destination needs attention. No backups found.')
        ->assertSeeText('No available backups')
        ->assertSeeText('No backups stored');
});

it('shows missing archives and withholds an incomplete total size', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    $backupListingData = backupListingDataForSummary([
        [
            'filename' => 'available.zip',
            'created_at' => '2026-10-21T12:00:00-06:00',
            'size_in_bytes' => 1024.0,
            'disk' => 'backups',
            'available' => true,
        ],
        [
            'filename' => 'missing.zip',
            'created_at' => null,
            'size_in_bytes' => null,
            'disk' => 'backups',
            'available' => false,
        ],
    ]);

    Livewire::test(BackupSummaryWidget::class, ['backupListingData' => backupListingWidgetData($backupListingData)])
        ->assertOk()
        ->assertSeeText('Available backups')
        ->assertSeeText('1')
        ->assertSeeText('Missing backups')
        ->assertSeeText('1')
        ->assertSeeText('Archives not found in storage')
        ->assertSeeText('Oct 21, 2026 12:00')
        ->assertSeeText('One or more archive sizes are unavailable');
});

it('does not expose the backup summary without view permission', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin);
    $backupListingData = backupListingDataForSummary([]);

    Livewire::test(BackupSummaryWidget::class, ['backupListingData' => backupListingWidgetData($backupListingData)])
        ->assertForbidden();
});

it('paginates backup archives ten rows at a time by default', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    Storage::fake('backups');

    foreach (range(1, 11) as $sequence) {
        storeBackupArchive(
            \sprintf('database-backup-%02d.zip', $sequence),
            'backup archive',
        );
    }

    $testable = backupManagementPageForTesting();
    $firstPageRecords = $testable->instance()->getTableRecords();

    throw_unless($firstPageRecords instanceof LengthAwarePaginator, LogicException::class, 'Backup records must use a length-aware paginator.');

    expect($firstPageRecords->total())->toBe(11)
        ->and($firstPageRecords->count())->toBe(10);

    $testable->call('setPage', 2);

    $secondPageRecords = $testable->instance()->getTableRecords();

    throw_unless($secondPageRecords instanceof LengthAwarePaginator, LogicException::class, 'Backup records must use a length-aware paginator.');

    expect($secondPageRecords->total())->toBe(11)
        ->and($secondPageRecords->count())->toBe(1);
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
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsCreate);
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
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($filename, 'backup archive');

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
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($filename, 'backup archive');
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
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($filename, 'backup archive');

    backupManagementPageForTesting()
        ->assertSeeText('Download')
        ->assertDontSeeText('Delete')
        ->assertDontSeeText('Restore');
});

it('shows the page title, navigation and create action in Spanish', function (): void {
    app()->setLocale('es');

    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsCreate);
    Queue::fake([RunDatabaseBackup::class]);
    Storage::fake('backups');

    $testable = backupManagementPageForTesting()
        ->assertSeeText('Gestión de respaldos')
        ->assertSeeText('Crear respaldo')
        ->call('mountAction', 'createBackup');

    $testable->__call('assertMountedActionModalSee', [[
        '¿Crear un respaldo de la base de datos?',
        'El respaldo se pondrá en cola',
        'Poner en cola',
    ]]);

    $testable->call('callMountedAction');

    Notification::assertNotified('Respaldo de la base de datos en cola');

    expect(BackupManagement::getNavigationLabel())->toBe('Respaldos')
        ->and(BackupManagement::getNavigationGroup())->toBe('Administración');
});

it('rejects an incorrect download password with a Spanish message', function (): void {
    app()->setLocale('es');

    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    $filename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($filename, 'backup archive');

    expect(fn () => resolve(BackupManagementService::class)->downloadBackup($user, $filename, 'not-the-password'))
        ->toThrow(ValidationException::class, 'La contraseña es incorrecta.');
});

it('shows the destination, retention and backups table in Spanish', function (): void {
    app()->setLocale('es');

    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    $user->saveAppAuthenticationSecret('filament-test-secret');

    config()->set('filesystems.disks.backups.bucket', 'social-hub-backups');
    config()->set('filesystems.disks.backups.endpoint', 'http://localhost:4566');
    Storage::fake('backups');

    storeBackupArchive((new RunDatabaseBackup)->filename, 'backup archive');

    $this->get(BackupManagement::getUrl(isAbsolute: false).'?locale=es')
        ->assertOk()
        ->assertSeeText('Destino de los respaldos')
        ->assertSeeText('Estado')
        ->assertSeeText('En buen estado')
        ->assertSeeText('Proveedor de almacenamiento')
        ->assertSeeText('Almacenamiento compatible con S3')
        ->assertSeeText('Privado')
        ->assertSeeText('Política de retención')
        ->assertSeeText('Todos los respaldos')
        ->assertSeeText('7 días')
        ->assertSeeText('4 semanas')
        ->assertSeeText('6 meses')
        ->assertDontSeeText('Backup destination');

    backupManagementPageForTesting()
        ->assertSeeText('Respaldos')
        ->assertSeeText('Archivo')
        ->assertSeeText('Tamaño')
        ->assertSeeText('Disponibilidad')
        ->assertSeeText('Disponible')
        ->assertDontSeeText('Availability');
});

it('shows the empty backups table and the download password prompt in Spanish', function (): void {
    app()->setLocale('es');

    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminBackupsDownload);
    Storage::fake('backups');

    backupManagementPageForTesting()
        ->assertSeeText('Aún no hay respaldos')
        ->assertSeeText('Un respaldo de la base de datos aparecerá aquí después de la primera ejecución.');

    $filename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($filename, 'backup archive');

    $testable = backupManagementPageForTesting()
        ->assertSeeText('Descargar')
        ->call('mountTableAction', 'downloadBackup', $filename);

    $testable->__call('assertMountedActionModalSee', [[
        'Confirma tu contraseña para descargar este respaldo',
        'Contraseña actual',
        'Descargar respaldo',
    ]]);
});

it('renders the backup summary in Spanish', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    $user->saveAppAuthenticationSecret('filament-test-secret');
    Storage::fake('backups');
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

    storeBackupArchive((new RunDatabaseBackup)->filename, str_repeat('b', 1024 * 1024), now()->subMinutes(30));

    $this->get(BackupManagement::getUrl(isAbsolute: false).'?locale=es')
        ->assertOk()
        ->assertSeeText('Resumen de respaldos')
        ->assertSeeText('Respaldos disponibles')
        ->assertSeeText('Archivos presentes en el almacenamiento')
        ->assertSeeText('Respaldos faltantes')
        ->assertSeeText('No faltan archivos')
        ->assertSeeText('Último respaldo disponible')
        ->assertSeeText('hace 30 minutos')
        ->assertSeeText('22 oct. 2026, 11:30')
        ->assertSeeText('Tamaño total de los respaldos')
        ->assertSeeText('Destino en buen estado.')
        ->assertDontSeeText('Backup summary');
});

it('shows the empty and unhealthy backup summary in Spanish', function (): void {
    app()->setLocale('es');

    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminBackupsReader,
    );
    $backupListingData = backupListingDataForSummary([], healthy: false);

    Livewire::test(BackupSummaryWidget::class, ['backupListingData' => backupListingWidgetData($backupListingData)])
        ->assertOk()
        ->assertSeeText('El destino necesita atención. No hay respaldos.')
        ->assertSeeText('No hay respaldos disponibles')
        ->assertSeeText('No hay respaldos guardados')
        ->assertSeeText('No disponible');
});
