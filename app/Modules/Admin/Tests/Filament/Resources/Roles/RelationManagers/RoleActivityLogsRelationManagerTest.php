<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleActivityLogsRelationManager;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

function roleWithHistory(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', 'admin')
        ->sole();
}

/**
 * @return Testable<Component>
 */
function roleActivityLogsRelationManagerFor(Role $role): Testable
{
    return Livewire::test(RoleActivityLogsRelationManager::class, [
        'ownerRecord' => $role,
        'pageClass' => ViewRole::class,
    ]);
}

function recordRolePermissionChange(Role $role, ActivityLogAdminEvent $activityLogAdminEvent): ActivityLog
{
    /** @var ActivityLogFactory $factory */
    $factory = ActivityLog::factory();

    return $factory->forEvent($activityLogAdminEvent)->performedOn($role)->createOne([
        'properties' => ['role' => $role->name, 'permission' => AdminPermission::AdminPostsView->value],
    ]);
}

/**
 * Record that the administrator gave the role to a user, as the service would.
 */
function recordRoleGrant(Role $role, User $user, ?User $administrator = null): ActivityLog
{
    $activityLogFactory = ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->performedOn($user);

    if ($administrator instanceof User) {
        $activityLogFactory = $activityLogFactory->causedBy($administrator);
    }

    return $activityLogFactory->createOne(['properties' => ['role' => $role->name]]);
}

/**
 * The keys of the activity rows the history shows.
 *
 * @param  Testable<Component>  $testable
 * @return list<string>
 */
function visibleRoleHistoryKeys(Testable $testable): array
{
    return FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), ActivityLog::class);
}

/**
 * Sign in an administrator who may read roles and the activity log.
 */
function actingAsRoleHistoryReader(TestCase $testCase): User
{
    return AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminActivityReader);
}

/**
 * Count the queries that rendering the history with the given page size runs, leaving out Spatie's
 * permission lookups, which depend on its cache and not on the rows.
 */
function countRoleHistoryQueries(Role $role, int $recordsPerPage): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    roleActivityLogsRelationManagerFor($role)->set('tableRecordsPerPage', $recordsPerPage)->assertOk();

    $historyQueries = array_filter(
        array_column(DB::getQueryLog(), 'query'),
        fn (string $query): bool => preg_match('/\b(permissions|role_has_permissions|model_has_permissions|model_has_roles)\b/', $query) !== 1,
    );

    DB::disableQueryLog();

    return count($historyQueries);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lists the permission changes of the role and not those of another role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminActivityReader);
    $role = roleWithHistory(AdminRole::AdminPostsReader);
    $activityLog = recordRolePermissionChange($role, ActivityLogAdminEvent::RolePermissionGranted);
    $revocation = recordRolePermissionChange($role, ActivityLogAdminEvent::RolePermissionRevoked);
    recordRolePermissionChange(roleWithHistory(AdminRole::AdminUsersReader), ActivityLogAdminEvent::RolePermissionGranted);

    $testable = roleActivityLogsRelationManagerFor($role)->assertOk();

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), ActivityLog::class))
        ->toEqualCanonicalizing([
            FilamentTableRecords::recordKey($activityLog),
            FilamentTableRecords::recordKey($revocation),
        ]);
});

it('shows the history title in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminActivityReader);

    expect(RoleActivityLogsRelationManager::getTitle(roleWithHistory(AdminRole::AdminPostsReader), ViewRole::class))
        ->toBe(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.relations.history.title'));
});

it('hides the tab from an administrator who cannot view the activity log', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = roleWithHistory(AdminRole::AdminPostsReader);

    expect(RoleActivityLogsRelationManager::canViewForRecord($role, ViewRole::class))->toBeFalse();

    roleActivityLogsRelationManagerFor($role)->assertForbidden();
});

it('lists the grants and revocations of the role and describes who received them', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleHistoryReader($testCase);
    $role = RoleFactory::new()->createOne();
    $user = createUser();
    $activityLog = recordRoleGrant($role, $user);
    $revocation = ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleRevoked)->performedOn($user)
        ->createOne(['properties' => ['role' => $role->name]]);
    recordRoleGrant(RoleFactory::new()->createOne(), $user);

    $testable = roleActivityLogsRelationManagerFor($role)
        ->assertSeeText(TranslatedText::of('admin/roles.relations.history.detail.role_granted', ['user' => $user->name]))
        ->assertSeeText(TranslatedText::of('admin/roles.relations.history.detail.role_revoked', ['user' => $user->name]))
        ->assertSeeText(TranslatedText::of('admin/roles.relations.history.change_types.users'));

    expect(visibleRoleHistoryKeys($testable))->toEqualCanonicalizing([
        FilamentTableRecords::recordKey($activityLog),
        FilamentTableRecords::recordKey($revocation),
    ]);
});

it('describes a permission change with the permission name', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleHistoryReader($testCase);
    $role = roleWithHistory(AdminRole::AdminPostsReader);
    recordRolePermissionChange($role, ActivityLogAdminEvent::RolePermissionGranted);

    roleActivityLogsRelationManagerFor($role)->assertSeeText(TranslatedText::of('admin/roles.relations.history.detail.permission_granted', [
        'permission' => AdminPermission::AdminPostsView->label(),
    ]));
});

it('groups the history by day, newest first', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleHistoryReader($testCase);
    $role = RoleFactory::new()->createOne();
    $activityLog = recordRoleGrant($role, createUser());
    $activityLog->forceFill(['created_at' => CarbonImmutable::parse('2026-10-01 10:00')])->save();
    $newerGrant = recordRoleGrant($role, createUser());
    $newerGrant->forceFill(['created_at' => CarbonImmutable::parse('2026-10-03 10:00')])->save();

    $testable = roleActivityLogsRelationManagerFor($role);

    expect(FilamentTableRecords::table($testable)->getTableGrouping()?->getId())->toBe('created_at')
        ->and(visibleRoleHistoryKeys($testable))->toBe([
            FilamentTableRecords::recordKey($newerGrant),
            FilamentTableRecords::recordKey($activityLog),
        ]);
});

it('filters the history by kind of change', function (string $changeType, string $expectedRecord): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleHistoryReader($testCase);
    $role = RoleFactory::new()->createOne();
    $records = [
        'users' => recordRoleGrant($role, createUser()),
        'permissions' => recordRolePermissionChange($role, ActivityLogAdminEvent::RolePermissionGranted),
        'lifecycle' => ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleCreated)->performedOn($role)
            ->createOne(['properties' => ['role' => $role->name, 'permissions' => []]]),
    ];

    $testable = roleActivityLogsRelationManagerFor($role);
    $testable->__call('filterTable', ['change_type', [$changeType]]);

    expect(visibleRoleHistoryKeys($testable))->toBe([FilamentTableRecords::recordKey($records[$expectedRecord])]);
})->with([
    ['users', 'users'],
    ['permissions', 'permissions'],
    ['lifecycle', 'lifecycle'],
]);

it('combines the kind of change, author and date filters', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleHistoryReader($testCase);
    $role = RoleFactory::new()->createOne();
    $user = createUser();
    $activityLog = recordRoleGrant($role, createUser(), $user);
    $activityLog->forceFill(['created_at' => CarbonImmutable::parse('2026-10-02 10:00')])->save();
    recordRoleGrant($role, createUser(), createUser())->forceFill(['created_at' => CarbonImmutable::parse('2026-10-02 11:00')])->save();
    recordRoleGrant($role, createUser(), $user)->forceFill(['created_at' => CarbonImmutable::parse('2026-09-20 10:00')])->save();
    recordRolePermissionChange($role, ActivityLogAdminEvent::RolePermissionGranted);

    $testable = roleActivityLogsRelationManagerFor($role);
    $testable->__call('filterTable', ['change_type', ['users']]);
    $testable->__call('filterTable', ['causer', ['causer_id' => $user->id]]);
    $testable->__call('filterTable', ['created_at', ['created_from' => '2026-10-01', 'created_until' => '2026-10-31']]);

    expect(visibleRoleHistoryKeys($testable))->toBe([FilamentTableRecords::recordKey($activityLog)]);
});

it('shows no rows for a kind of change without activity', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleHistoryReader($testCase);
    $role = RoleFactory::new()->createOne();
    recordRoleGrant($role, createUser());

    $testable = roleActivityLogsRelationManagerFor($role);
    $testable->__call('filterTable', ['change_type', ['lifecycle']]);

    expect(visibleRoleHistoryKeys($testable))
        ->toBeEmpty();
});

it('runs the same number of queries for 10 and for 50 rows', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleHistoryReader($testCase);
    $role = RoleFactory::new()->createOne();

    foreach (range(1, 25) as $index) {
        recordRoleGrant($role, createUser(), createUser());
        $sourceRole = RoleFactory::new()->createOne();
        ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleDuplicated)->performedOn($role)->causedBy(createUser())
            ->createOne(['properties' => ['role' => $role->name, 'source_role_id' => $sourceRole->id]]);
    }

    expect(countRoleHistoryQueries($role, 50))->toBe(countRoleHistoryQueries($role, 10));
});
