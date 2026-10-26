<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Data\DashboardBackupSummaryData;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Widgets\OperationalSummaryWidget;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

function createOperationalDashboardAdmin(AdminPermission ...$adminPermissions): User
{
    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    if ($adminPermissions !== []) {
        $user->givePermissionTo(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->value,
            $adminPermissions,
        ));
    }

    return $user;
}

function createDashboardStatusChange(User $user, User $actor, AccountStatus $accountStatus, string $createdAt): AccountStatusChange
{
    /** @var AccountStatusChangeFactory $accountStatusChangeFactory */
    $accountStatusChangeFactory = AccountStatusChange::factory();

    return $accountStatusChangeFactory
        ->for($user)
        ->changedBy($actor)
        ->createOne([
            'status' => $accountStatus,
            'created_at' => $createdAt,
        ]);
}

function createDashboardActivityEvent(ActivityLogAuthEvent $activityLogAuthEvent, string $createdAt): ActivityLog
{
    return ActivityLogFactory::new()
        ->forEvent($activityLogAuthEvent)
        ->createOne(['created_at' => $createdAt]);
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    config()->set('backup.backup.name', 'social-hub-test');
    config()->set('backup.monitor_backups.0.name', 'social-hub-test');
    Cache::forget('admin:dashboard:backup-summary:v1');

    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows account counts and status changes within the seven-day window', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminUsersViewAny);
    $this->actingAs($authenticatedUser, 'admin');

    $activeUser = createUser();
    $suspendedUser = createUser();
    $suspendedUser->forceFill(['status' => AccountStatus::Suspended])->save();

    createDashboardStatusChange($activeUser, $authenticatedUser, AccountStatus::Suspended, '2026-06-09 00:00:00');
    createDashboardStatusChange($suspendedUser, $authenticatedUser, AccountStatus::Active, '2026-06-15 23:59:59');
    createDashboardStatusChange($suspendedUser, $authenticatedUser, AccountStatus::Active, '2026-06-08 23:59:59');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Account status')
        ->assertSeeText('2 active · 1 suspended')
        ->assertSeeText('2 status changes in the last 7 days')
        ->assertSee(UserResource::getUrl('index', panel: 'admin'));
});

it('shows the activity count for the seven-day window', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminActivityViewAny);
    $this->actingAs($authenticatedUser, 'admin');

    createDashboardActivityEvent(ActivityLogAuthEvent::UserLoggedIn, '2026-06-09 00:00:00');
    createDashboardActivityEvent(ActivityLogAuthEvent::UserLoggedOut, '2026-06-15 23:59:59');
    createDashboardActivityEvent(ActivityLogAuthEvent::LoginFailed, '2026-06-08 23:59:59');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Activity (7 days)')
        ->assertSeeText('2')
        ->assertSee(ActivityLogResource::getUrl('index', panel: 'admin'));
});

it('shows the age and availability of the newest backup', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminBackupsView);
    $this->actingAs($authenticatedUser, 'admin');
    Storage::fake('backups');

    $olderBackupFilename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($olderBackupFilename, 'older backup', now()->subHours(2));
    $latestBackupFilename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($latestBackupFilename, 'latest backup', now()->subMinutes(30));

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Latest backup')
        ->assertSeeText('30 minutes ago')
        ->assertSeeText('2/2 available')
        ->assertSee(BackupManagement::getUrl(panel: 'admin'));
});

it('does not render or cache backup filenames', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminBackupsView);
    $this->actingAs($authenticatedUser, 'admin');
    Storage::fake('backups');

    $backupFilename = (new RunDatabaseBackup)->filename;
    storeBackupArchive($backupFilename, 'backup archive');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertDontSeeText($backupFilename);

    $cachedBackupSummary = Cache::get('admin:dashboard:backup-summary:v1');
    throw_unless($cachedBackupSummary instanceof DashboardBackupSummaryData, LogicException::class, 'The cached backup summary must contain aggregate data.');

    expect(serialize($cachedBackupSummary))->not->toContain($backupFilename);
});

it('shows only account metrics when the user has no activity or backup permissions', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminUsersViewAny);
    $this->actingAs($authenticatedUser, 'admin');

    $queries = [];
    DB::listen(static function (QueryExecuted $queryExecuted) use (&$queries): void {
        $queries[] = strtolower($queryExecuted->sql);
    });

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Account status')
        ->assertDontSeeText('Activity (7 days)')
        ->assertDontSeeText('Latest backup')
        ->assertSee(UserResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(ActivityLogResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(BackupManagement::getUrl(panel: 'admin'));

    expect(array_filter($queries, static fn (string $query): bool => str_contains($query, 'activity_log')))
        ->toBeEmpty()
        ->and(Cache::has('admin:dashboard:backup-summary:v1'))
        ->toBeFalse();
});

it('shows only recent activity when the user has no account or backup permissions', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminActivityViewAny);
    $this->actingAs($authenticatedUser, 'admin');

    ActivityLogFactory::new()
        ->forEvent(ActivityLogAuthEvent::UserLoggedIn)
        ->createOne(['created_at' => '2026-06-15 11:00:00']);

    $queries = [];
    DB::listen(static function (QueryExecuted $queryExecuted) use (&$queries): void {
        $queries[] = strtolower($queryExecuted->sql);
    });

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Activity (7 days)')
        ->assertSeeText('Recorded events in the activity log')
        ->assertDontSeeText('Account status')
        ->assertDontSeeText('Latest backup')
        ->assertSee(ActivityLogResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(UserResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(BackupManagement::getUrl(panel: 'admin'));

    expect(array_filter(
        $queries,
        static fn (string $query): bool => str_contains($query, 'account_status_changes')
            || (str_contains($query, 'count(*)') && str_contains($query, 'users')),
    ))
        ->toBeEmpty()
        ->and(Cache::has('admin:dashboard:backup-summary:v1'))
        ->toBeFalse();
});

it('shows only backup metrics when the user has no account or activity permissions', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminBackupsView);
    $this->actingAs($authenticatedUser, 'admin');
    Storage::fake('backups');
    storeBackupArchive((new RunDatabaseBackup)->filename, 'dashboard backup');

    $queries = [];
    DB::listen(static function (QueryExecuted $queryExecuted) use (&$queries): void {
        $queries[] = strtolower($queryExecuted->sql);
    });

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Latest backup')
        ->assertSeeText('1/1 available')
        ->assertDontSeeText('Account status')
        ->assertDontSeeText('Activity (7 days)')
        ->assertSee(BackupManagement::getUrl(panel: 'admin'))
        ->assertDontSee(UserResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(ActivityLogResource::getUrl('index', panel: 'admin'));

    expect(array_filter(
        $queries,
        static fn (string $query): bool => str_contains($query, 'account_status_changes')
            || str_contains($query, 'activity_log')
            || (str_contains($query, 'count(*)') && str_contains($query, 'users')),
    ))
        ->toBeEmpty();
});

it('shows an explicit unavailable value when no backups exist', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminBackupsView);
    $this->actingAs($authenticatedUser, 'admin');
    Storage::fake('backups');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Latest backup')
        ->assertSeeText('Unavailable')
        ->assertSeeText('No backups found · 0/0 available');
});

it('refreshes its aggregate backup summary after the five minute cache expires', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminBackupsView);
    $this->actingAs($authenticatedUser, 'admin');
    Storage::fake('backups');

    storeBackupArchive((new RunDatabaseBackup)->filename, 'first backup');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('1/1 available');

    $newBackupFilename = (new RunDatabaseBackup)->filename;
    $this->travel(3)->minutes();
    storeBackupArchive($newBackupFilename, 'second backup');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('1/1 available');

    $this->travel(3)->minutes();

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('2/2 available');
});

it('renders a zero activity count when the log has no recent events', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(AdminPermission::AdminActivityViewAny);
    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Activity (7 days)')
        ->assertSeeText('Recorded events in the activity log')
        ->assertSeeText('0');
});

it('keeps activity metrics available when the backup source fails', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin(
        AdminPermission::AdminActivityViewAny,
        AdminPermission::AdminBackupsView,
    );
    $this->actingAs($authenticatedUser, 'admin');
    createDashboardActivityEvent(ActivityLogAuthEvent::UserLoggedIn, '2026-06-15 11:00:00');
    config()->set('backup.backup.destination.disks', []);
    Storage::fake('backups');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertSeeText('Activity (7 days)')
        ->assertSeeText('1')
        ->assertSeeText('Latest backup')
        ->assertSeeText('Backup summary could not be loaded');
});

it('forbids the operational widget when the user has no source permissions', function (): void {
    $authenticatedUser = createOperationalDashboardAdmin();
    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(OperationalSummaryWidget::class)
        ->assertForbidden();
});
