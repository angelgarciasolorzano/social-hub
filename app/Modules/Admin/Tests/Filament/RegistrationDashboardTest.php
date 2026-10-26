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
use App\Modules\Admin\Filament\Widgets\RegistrationsChart;
use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

function createDashboardRegistration(string $createdAt, bool $verified = true): User
{
    $user = createUser();

    $user->forceFill([
        'created_at' => $createdAt,
        'email_verified_at' => $verified ? now() : null,
    ])->save();

    return $user;
}

function createDashboardReader(): User
{
    $user = createUser();

    $user->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminDashboardReader->value,
    ]);

    $user->forceFill(['created_at' => '2020-01-01 00:00:00'])->save();

    return $user;
}

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

/**
 * @return array{datasets: list<array{label: string, data: list<int>}>, labels: list<string>}
 */
function registrationChartPayload(RegistrationsChart $registrationsChart): array
{
    $cachedDataMethod = new ReflectionMethod(ChartWidget::class, 'getCachedData');
    $chartData = $cachedDataMethod->invoke($registrationsChart);

    $labels = is_array($chartData) ? ($chartData['labels'] ?? null) : null;
    $datasets = is_array($chartData) ? ($chartData['datasets'] ?? null) : null;
    $firstDataset = is_array($datasets) ? ($datasets[0] ?? null) : null;
    $datasetLabel = is_array($firstDataset) ? ($firstDataset['label'] ?? null) : null;
    $counts = is_array($firstDataset) ? ($firstDataset['data'] ?? null) : null;

    throw_unless(
        is_array($labels)
            && array_is_list($labels)
            && is_array($counts)
            && array_is_list($counts)
            && is_string($datasetLabel),
        LogicException::class,
        'The registration chart must return its chart data.',
    );

    /** @var list<mixed> $labels */
    /** @var list<mixed> $counts */
    /** @var string $datasetLabel */
    $normalizedLabels = [];

    foreach ($labels as $label) {
        throw_unless(is_string($label), LogicException::class, 'The chart registration labels must be strings.');
        $normalizedLabels[] = $label;
    }

    $normalizedCounts = [];

    foreach ($counts as $count) {
        throw_unless(is_int($count), LogicException::class, 'The chart registration counts must be integers.');
        $normalizedCounts[] = $count;
    }

    return [
        'datasets' => [[
            'label' => $datasetLabel,
            'data' => $normalizedCounts,
        ]],
        'labels' => $normalizedLabels,
    ];
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

it('discovers the registration widgets through the existing admin panel discovery', function (): void {
    expect(Filament::getPanel('admin')->getWidgets())
        ->toContain(RegistrationsChart::class, RegistrationSummaryWidget::class, OperationalSummaryWidget::class);
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

it('renders both registration widgets for dashboard readers', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(RegistrationSummaryWidget::class)
        ->assertSeeText('Total accounts');

    Livewire::test(RegistrationsChart::class)
        ->assertSeeText('New user registrations');
});

it('forbids panel users without dashboard permission from both registration widgets', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);

    expect($authenticatedUser->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(RegistrationSummaryWidget::class)
        ->assertForbidden();

    Livewire::test(RegistrationsChart::class)
        ->assertForbidden();
});

it('groups registrations by day for the seven day range and includes its exact boundaries', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    createDashboardRegistration('2026-06-09 00:00:00');
    createDashboardRegistration('2026-06-10 10:00:00');
    createDashboardRegistration('2026-06-10 18:00:00');
    createDashboardRegistration('2026-06-08 23:59:59');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => '7_days'])->instance();

    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    /** @var RegistrationsChart $component */
    $chartData = registrationChartPayload($component);

    expect($chartData['labels'])
        ->toHaveCount(7)
        ->toBe(['Jun 9', 'Jun 10', 'Jun 11', 'Jun 12', 'Jun 13', 'Jun 14', 'Jun 15'])
        ->and($chartData['datasets'][0]['data'])
        ->toBe([1, 2, 0, 0, 0, 0, 0]);
});

it('groups registrations by day for the thirty day range', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    createDashboardRegistration('2026-05-17 00:00:00');
    createDashboardRegistration('2026-06-15 23:59:59');
    createDashboardRegistration('2026-05-16 23:59:59');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => '30_days'])->instance();

    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    /** @var RegistrationsChart $component */
    $chartData = registrationChartPayload($component);

    expect($chartData['labels'])
        ->toHaveCount(30)
        ->and(array_slice($chartData['labels'], 0, 1))
        ->toBe(['May 17'])
        ->and(array_slice($chartData['labels'], -1))
        ->toBe(['Jun 15'])
        ->and(array_sum($chartData['datasets'][0]['data']))
        ->toBe(2);
});

it('groups registrations by week for the ninety day range', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    createDashboardRegistration('2026-03-18 00:00:00');
    createDashboardRegistration('2026-03-17 23:59:59');
    createDashboardRegistration('2026-06-10 10:00:00');
    createDashboardRegistration('2026-06-14 18:00:00');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => '90_days'])->instance();

    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    /** @var RegistrationsChart $component */
    $chartData = registrationChartPayload($component);

    $weeklyCounts = array_combine($chartData['labels'], $chartData['datasets'][0]['data']);

    expect($chartData['labels'])
        ->toHaveCount(14)
        ->and(array_slice($chartData['labels'], 0, 1))
        ->toBe(['Mar 16'])
        ->and(array_slice($chartData['labels'], -1))
        ->toBe(['Jun 15'])
        ->and($weeklyCounts['Mar 16'])
        ->toBe(1)
        ->and($weeklyCounts['Jun 8'])
        ->toBe(2);
});

it('groups registrations by month for the twelve month range', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    createDashboardRegistration('2025-07-01 00:00:00');
    createDashboardRegistration('2025-06-30 23:59:59');
    createDashboardRegistration('2026-06-01 10:00:00');
    createDashboardRegistration('2026-06-15 18:00:00');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => '12_months'])->instance();

    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    /** @var RegistrationsChart $component */
    $chartData = registrationChartPayload($component);

    $monthlyCounts = array_combine($chartData['labels'], $chartData['datasets'][0]['data']);

    expect($chartData['labels'])
        ->toHaveCount(12)
        ->and(array_slice($chartData['labels'], 0, 1))
        ->toBe(['Jul 2025'])
        ->and(array_slice($chartData['labels'], -1))
        ->toBe(['Jun 2026'])
        ->and($monthlyCounts['Jul 2025'])
        ->toBe(1)
        ->and($monthlyCounts['Jun 2026'])
        ->toBe(2);
});

it('falls back to the thirty day range for an unknown client supplied filter', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    createDashboardRegistration('2026-05-17 00:00:00');
    createDashboardRegistration('2026-05-16 23:59:59');

    $component = Livewire::test(RegistrationsChart::class, ['filter' => 'untrusted-filter-value'])->instance();

    throw_unless($component instanceof RegistrationsChart, LogicException::class, 'The chart widget must be mounted.');

    /** @var RegistrationsChart $component */
    $chartData = registrationChartPayload($component);

    expect($chartData['labels'])
        ->toHaveCount(30)
        ->and(array_sum($chartData['datasets'][0]['data']))
        ->toBe(1);
});

it('renders filaments empty state when the selected period has no registrations', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(RegistrationsChart::class)
        ->assertSeeText('No registrations yet')
        ->assertSeeText('There are no account registrations in this period.');
});

it('shows accurate total and verified account summaries', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    createDashboardRegistration('2026-06-10 10:00:00');
    createDashboardRegistration('2026-06-11 10:00:00', verified: false);

    Livewire::test(RegistrationSummaryWidget::class)
        ->assertSeeText('Total accounts')
        ->assertSeeText('All registered user accounts')
        ->assertSeeText('Verified accounts')
        ->assertSeeText('Accounts with a verified email address')
        ->assertSeeText('3')
        ->assertSeeText('2');
});

it('shows seven day registration trends on the account summary cards', function (): void {
    $authenticatedUser = createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    createDashboardRegistration('2026-06-09 08:00:00');
    createDashboardRegistration('2026-06-10 08:00:00', verified: false);
    createDashboardRegistration('2026-06-10 09:00:00');
    createDashboardRegistration('2026-06-15 08:00:00', verified: false);

    $component = Livewire::test(RegistrationSummaryWidget::class)->instance();

    throw_unless($component instanceof RegistrationSummaryWidget, LogicException::class, 'The summary widget must be mounted.');

    /** @var RegistrationSummaryWidget $component */
    $statsMethod = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats');

    $stats = $statsMethod->invoke($component);

    throw_unless(
        is_array($stats)
            && count($stats) === 2
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat,
        LogicException::class,
        'The account summary must return two stat cards.',
    );

    /** @var array{0: Stat, 1: Stat} $stats */
    expect($stats[0]->getChart())
        ->toBe([1.0, 2.0, 0.0, 0.0, 0.0, 0.0, 1.0])
        ->and($stats[1]->getChart())
        ->toBe([1.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
});
