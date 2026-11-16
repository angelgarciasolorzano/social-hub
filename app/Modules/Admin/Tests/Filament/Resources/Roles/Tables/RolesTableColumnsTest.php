<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The roles list on the given tab with every role on one page.
 *
 * @return Testable<ListRoles>
 */
function rolesColumnsList(string $tab = 'all'): Testable
{
    return Livewire::test(ListRoles::class)
        ->set('activeTab', $tab)
        ->call('loadTable')
        ->set('tableRecordsPerPage', 50);
}

/**
 * Record an activity of the role by the given administrator at the given time.
 */
function recordListedRoleActivity(Role $role, ActivityLogAdminEvent $activityLogAdminEvent, User $user, string $recordedAt): void
{
    ActivityLogFactory::new()
        ->forEvent($activityLogAdminEvent)
        ->performedOn($role)
        ->causedBy($user)
        ->createOne(['properties' => ['role' => $role->name], 'created_at' => CarbonImmutable::parse($recordedAt)]);
}

/**
 * A custom role created and then changed by different administrators.
 */
function changedCustomRole(User $creator, User $editor, string $changedAt = '2026-10-10 10:00:00'): Role
{
    $role = RoleFactory::new()->createOne();

    recordListedRoleActivity($role, ActivityLogAdminEvent::RoleCreated, $creator, '2026-09-01 10:00:00');
    recordListedRoleActivity($role, ActivityLogAdminEvent::RolePermissionGranted, $editor, $changedAt);

    return $role;
}

/**
 * Count the queries that rendering the custom roles tab runs.
 */
function countCustomRolesListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    rolesColumnsList('custom')->assertOk();

    $queryCount = count(DB::connection()->getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
});

it('shows the areas, the latest change with its author and the creator of a custom role', function (): void {
    $user = createUser();
    $editor = createUser();
    $role = changedCustomRole($user, $editor);
    $role->givePermissionTo([AdminPermission::AdminBackupsView->value, AdminPermission::AdminPostsViewAny->value]);

    $testable = rolesColumnsList('custom');
    $testable->__call('assertTableColumnStateSet', ['areas', [
        TranslatedText::of('admin/roles.catalog.areas.posts'),
        TranslatedText::of('admin/roles.catalog.areas.backups'),
    ], $role]);
    $testable->__call('assertTableColumnStateSet', ['last_changed_at', CarbonImmutable::parse('2026-10-10 10:00:00'), $role]);
    $testable->__call('assertTableColumnHasDescription', ['last_changed_at', $editor->name, $role]);
    $testable->__call('assertTableColumnStateSet', ['created_by', $user->name, $role]);
});

it('shows not recorded for a seeded system role without activity', function (): void {
    $systemRole = Role::query()->where('name', AdminRole::AdminPostsReader->value)->where('guard_name', Role::GUARD_NAME)->sole();

    $testable = rolesColumnsList('system');
    $testable->__call('assertTableColumnStateSet', ['last_changed_at', null, $systemRole]);
    $testable->__call('assertTableColumnStateSet', ['created_by', TranslatedText::of('admin/roles.table.not_recorded'), $systemRole]);
    $testable->assertSee(TranslatedText::of('admin/roles.table.not_recorded'));
});

it('groups the roles by type by default', function (): void {
    rolesColumnsList()
        ->assertSee(TranslatedText::of('admin/roles.types.system'))
        ->assertSet('tableGrouping', 'is_system:asc');
});

it('sorts the roles by their latest change in both directions', function (string $direction): void {
    $user = createUser();
    $earlierRole = changedCustomRole($user, $user, '2026-10-01 10:00:00');
    $laterRole = changedCustomRole($user, $user, '2026-10-05 10:00:00');

    $testable = rolesColumnsList('custom');
    $testable->__call('sortTable', ['last_changed_at', $direction]);

    $expectedOrder = [$earlierRole->name, $laterRole->name];

    expect(array_map(
        static fn (Role $role): string => $role->name,
        FilamentTableRecords::visibleRecords(FilamentTableRecords::table($testable), Role::class),
    ))->toBe($direction === 'asc' ? $expectedOrder : array_reverse($expectedOrder));
})->with(['asc', 'desc']);

it('runs the same number of queries with few and many changed roles', function (): void {
    foreach (range(1, 5) as $index) {
        changedCustomRole(createUser(), createUser());
    }

    countCustomRolesListQueries();
    $queriesWithFewRoles = countCustomRolesListQueries();

    foreach (range(1, 15) as $index) {
        changedCustomRole(createUser(), createUser());
    }

    countCustomRolesListQueries();

    expect(countCustomRolesListQueries())->toBe($queriesWithFewRoles);
});

it('hides the creator column by default and shows it when toggled on', function (): void {
    $testable = rolesColumnsList('custom');
    $testable->__call('assertCanNotRenderTableColumn', ['created_by']);
    $testable->__call('toggleAllTableColumns', []);
    $testable->__call('assertCanRenderTableColumn', ['created_by']);
});
