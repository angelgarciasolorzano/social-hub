<?php

declare(strict_types=1);

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
use App\Modules\Admin\Tests\Support\QueryLog;
use App\Modules\Admin\Tests\Support\RoleActivity;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

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

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lists the permission changes of the role and not those of another role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);
    $role = adminCatalogRole(AdminRole::AdminPostsReader);
    $activityLog = RoleActivity::change($role, ActivityLogAdminEvent::RolePermissionGranted, properties: ['permission' => AdminPermission::AdminPostsView->value]);
    $revocation = RoleActivity::change($role, ActivityLogAdminEvent::RolePermissionRevoked, properties: ['permission' => AdminPermission::AdminPostsView->value]);
    RoleActivity::change(adminCatalogRole(AdminRole::AdminUsersReader), ActivityLogAdminEvent::RolePermissionGranted, properties: ['permission' => AdminPermission::AdminPostsView->value]);

    $testable = roleActivityLogsRelationManagerFor($role)->assertOk();

    expect(FilamentTableRecords::shownKeys($testable, ActivityLog::class))
        ->toEqualCanonicalizing([
            FilamentTableRecords::recordKey($activityLog),
            FilamentTableRecords::recordKey($revocation),
        ]);
});

it('shows the history title in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);

    expect(RoleActivityLogsRelationManager::getTitle(adminCatalogRole(AdminRole::AdminPostsReader), ViewRole::class))
        ->toBe(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.relations.history.title'));
});

it('hides the tab from an administrator who cannot view the activity log', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase);
    $role = adminCatalogRole(AdminRole::AdminPostsReader);

    expect(RoleActivityLogsRelationManager::canViewForRecord($role, ViewRole::class))->toBeFalse();

    roleActivityLogsRelationManagerFor($role)->assertForbidden();
});

it('lists the grants and revocations of the role and describes who received them', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);
    $role = RoleFactory::new()->createOne();
    $user = createUser();
    $activityLog = RoleActivity::grant($role, $user);
    $revocation = RoleActivity::record(ActivityLogAdminEvent::RoleRevoked, $user, ['role' => $role->name]);
    RoleActivity::grant(RoleFactory::new()->createOne(), $user);

    $testable = roleActivityLogsRelationManagerFor($role)
        ->assertSeeText(TranslatedText::of('admin/roles.relations.history.detail.role_granted', ['user' => $user->name]))
        ->assertSeeText(TranslatedText::of('admin/roles.relations.history.detail.role_revoked', ['user' => $user->name]))
        ->assertSeeText(TranslatedText::of('admin/roles.relations.history.change_types.users'));

    expect(FilamentTableRecords::shownKeys($testable, ActivityLog::class))->toEqualCanonicalizing([
        FilamentTableRecords::recordKey($activityLog),
        FilamentTableRecords::recordKey($revocation),
    ]);
});

it('describes a permission change with the permission name', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);
    $role = adminCatalogRole(AdminRole::AdminPostsReader);
    RoleActivity::change($role, ActivityLogAdminEvent::RolePermissionGranted, properties: ['permission' => AdminPermission::AdminPostsView->value]);

    roleActivityLogsRelationManagerFor($role)->assertSeeText(TranslatedText::of('admin/roles.relations.history.detail.permission_granted', [
        'permission' => AdminPermission::AdminPostsView->label(),
    ]));
});

it('groups the history by day, newest first', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);
    $role = RoleFactory::new()->createOne();
    $activityLog = RoleActivity::grant($role, createUser(), at: '2026-10-01 10:00');
    $newerGrant = RoleActivity::grant($role, createUser(), at: '2026-10-03 10:00');

    $testable = roleActivityLogsRelationManagerFor($role);

    expect(FilamentTableRecords::table($testable)->getTableGrouping()?->getId())->toBe('created_at')
        ->and(FilamentTableRecords::shownKeys($testable, ActivityLog::class))->toBe([
            FilamentTableRecords::recordKey($newerGrant),
            FilamentTableRecords::recordKey($activityLog),
        ]);
});

it('filters the history by kind of change', function (string $changeType, string $expectedRecord): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);
    $role = RoleFactory::new()->createOne();
    $records = [
        'users' => RoleActivity::grant($role, createUser()),
        'permissions' => RoleActivity::change($role, ActivityLogAdminEvent::RolePermissionGranted, properties: ['permission' => AdminPermission::AdminPostsView->value]),
        'lifecycle' => RoleActivity::record(ActivityLogAdminEvent::RoleCreated, $role, ['role' => $role->name, 'permissions' => []]),
    ];

    $testable = roleActivityLogsRelationManagerFor($role);
    $testable->__call('filterTable', ['change_type', [$changeType]]);

    expect(FilamentTableRecords::shownKeys($testable, ActivityLog::class))->toBe([FilamentTableRecords::recordKey($records[$expectedRecord])]);
})->with([
    ['users', 'users'],
    ['permissions', 'permissions'],
    ['lifecycle', 'lifecycle'],
]);

it('combines the kind of change, author and date filters', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);
    $role = RoleFactory::new()->createOne();
    $user = createUser();
    $activityLog = RoleActivity::grant($role, createUser(), $user, '2026-10-02 10:00');
    RoleActivity::grant($role, createUser(), createUser(), '2026-10-02 11:00');
    RoleActivity::grant($role, createUser(), $user, '2026-09-20 10:00');
    RoleActivity::change($role, ActivityLogAdminEvent::RolePermissionGranted, properties: ['permission' => AdminPermission::AdminPostsView->value]);

    $testable = roleActivityLogsRelationManagerFor($role);
    $testable->__call('filterTable', ['change_type', ['users']]);
    $testable->__call('filterTable', ['causer', ['causer_id' => $user->id]]);
    $testable->__call('filterTable', ['created_at', ['created_from' => '2026-10-01', 'created_until' => '2026-10-31']]);

    expect(FilamentTableRecords::shownKeys($testable, ActivityLog::class))->toBe([FilamentTableRecords::recordKey($activityLog)]);
});

it('shows no rows for a kind of change without activity', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);
    $role = RoleFactory::new()->createOne();
    RoleActivity::grant($role, createUser());

    $testable = roleActivityLogsRelationManagerFor($role);
    $testable->__call('filterTable', ['change_type', ['lifecycle']]);

    expect(FilamentTableRecords::shownKeys($testable, ActivityLog::class))
        ->toBeEmpty();
});

it('runs the same number of queries for 10 and for 50 rows', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsRolesReader($testCase, AdminRole::AdminActivityReader);
    $role = RoleFactory::new()->createOne();

    foreach (range(1, 25) as $index) {
        RoleActivity::grant($role, createUser());
        $sourceRole = RoleFactory::new()->createOne();
        RoleActivity::change($role, ActivityLogAdminEvent::RoleDuplicated, properties: ['source_role_id' => $sourceRole->id]);
    }

    $renderHistory = static fn (int $recordsPerPage): Closure => static fn (): Testable => roleActivityLogsRelationManagerFor($role)
        ->set('tableRecordsPerPage', $recordsPerPage)
        ->assertOk();
    // Spatie's permission lookups depend on its cache, not on the rows.
    $keepHistoryQuery = static fn (string $query): bool => preg_match('/\b(permissions|role_has_permissions|model_has_permissions|model_has_roles)\b/', $query) !== 1;

    expect(QueryLog::count($renderHistory(50), $keepHistoryQuery))->toBe(QueryLog::count($renderHistory(10), $keepHistoryQuery));
});
