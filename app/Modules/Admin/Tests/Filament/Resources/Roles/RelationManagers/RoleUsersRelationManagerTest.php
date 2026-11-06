<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleUsersRelationManager;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

function roleWithUsers(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', 'admin')
        ->sole();
}

/**
 * @return Testable<Component>
 */
function roleUsersRelationManagerFor(Role $role): Testable
{
    return Livewire::test(RoleUsersRelationManager::class, [
        'ownerRecord' => $role,
        'pageClass' => ViewRole::class,
    ]);
}

/**
 * The names of the given table actions, in order.
 *
 * @param  array<mixed>  $actions
 * @return list<string|null>
 */
function tableActionNames(array $actions): array
{
    $actionNames = [];

    foreach ($actions as $action) {
        $actionNames[] = $action instanceof Action ? $action->getName() : null;
    }

    return $actionNames;
}

/**
 * Give the role to a user and record the grant as the service would, made by the administrator at the
 * given time.
 */
function grantRoleWithActivity(Role $role, User $user, User $administrator, string $grantedAt): void
{
    $user->assignRole($role);

    ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->performedOn($user)->causedBy($administrator)
        ->createOne(['properties' => ['role' => $role->name], 'created_at' => CarbonImmutable::parse($grantedAt)]);
}

/**
 * Count the queries that rendering the users tab with the given page size runs, leaving out Spatie's
 * permission lookups, which depend on its cache and not on the rows. Tests render once before measuring so
 * the signed-in administrator's own roles are already loaded.
 */
function countRoleUsersQueries(Role $role, int $recordsPerPage): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    roleUsersRelationManagerFor($role)->set('tableRecordsPerPage', $recordsPerPage)->assertOk();

    $tabQueries = array_filter(
        array_column(DB::getQueryLog(), 'query'),
        fn (string $query): bool => preg_match('/\b(permissions|role_has_permissions|model_has_permissions)\b/', $query) !== 1
            || str_contains($query, 'activity_log'),
    );

    DB::disableQueryLog();

    return count($tabQueries);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lists only the users holding the role, linked to their detail', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $user = createUser();
    $user->assignRole(AdminRole::AdminPostsReader->value);

    $suspendedPostsReader = createSuspendedUser();
    $suspendedPostsReader->assignRole(AdminRole::AdminPostsReader->value);

    createUser()->assignRole(AdminRole::AdminUsersReader->value);

    $testable = roleUsersRelationManagerFor(roleWithUsers(AdminRole::AdminPostsReader))
        ->assertOk()
        ->assertSee($user->email)
        ->assertSee('Suspended')
        ->assertSee(UserResource::getUrl('view', ['record' => $user]), false);

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), User::class))
        ->toEqualCanonicalizing([
            FilamentTableRecords::recordKey($user),
            FilamentTableRecords::recordKey($suspendedPostsReader),
        ]);
});

it('offers adding, removing and removing several users, but no create or edit actions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    $table = FilamentTableRecords::table(roleUsersRelationManagerFor(roleWithUsers(AdminRole::AdminPostsReader)))->getTable();

    expect(tableActionNames($table->getHeaderActions()))->toBe(['addUsers'])
        ->and(tableActionNames($table->getRecordActions()))->toBe(['removeFromRole'])
        ->and(tableActionNames($table->getToolbarActions()))->toBe(['removeSelectedFromRole']);
});

it('shows an empty state in Spanish for a role nobody holds', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    roleUsersRelationManagerFor(roleWithUsers(AdminRole::AdminBackupsOperator))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.relations.users.empty'));

    expect(RoleUsersRelationManager::getTitle(roleWithUsers(AdminRole::AdminBackupsOperator), ViewRole::class))
        ->toBe(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.relations.users.title'));
});

it('hides the tab from a roles reader who cannot list users', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = roleWithUsers(AdminRole::AdminPostsReader);

    expect(RoleUsersRelationManager::canViewForRecord($role, ViewRole::class))->toBeFalse();

    roleUsersRelationManagerFor($role)->assertForbidden();
});

it('adds two users to a custom role and removes one, with one activity per change', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();
    $firstUser = createUser();
    $secondUser = createUser();

    roleUsersRelationManagerFor($customRole)
        ->call('mountTableAction', 'addUsers')
        ->set('mountedActions.0.data.users', [$firstUser->id, $secondUser->id])
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction')
        ->assertHasNoErrors();

    roleUsersRelationManagerFor($customRole)
        ->call('mountTableAction', 'removeFromRole', $firstUser->getKey())
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    $activityLogs = ActivityLog::query()->where('log_name', 'admin')->get();

    expect($firstUser->refresh()->hasRole($customRole))->toBeFalse()
        ->and($secondUser->refresh()->hasRole($customRole))->toBeTrue()
        ->and($activityLogs->pluck('event')->sort()->values()->all())->toBe([
            ActivityLogAdminEvent::RoleGranted->value,
            ActivityLogAdminEvent::RoleGranted->value,
            ActivityLogAdminEvent::RoleRevoked->value,
        ])
        ->and($activityLogs->pluck('causer_id')->unique()->all())->toBe([$user->id]);
});

it('never offers the administrator own account', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $otherUser = createUser();
    $role = roleWithUsers(AdminRole::AdminRolesManager);

    $testable = roleUsersRelationManagerFor($role);
    $searchResults = new ReflectionMethod(RoleUsersRelationManager::class, 'addableUserOptions')->invoke($testable->instance(), '');

    expect($searchResults)->toBeArray()->toHaveKey($otherUser->id)->not->toHaveKey($user->id);

    $testable->__call('assertTableActionHidden', ['removeFromRole', $user]);

    roleUsersRelationManagerFor($role)
        ->call('mountTableAction', 'addUsers')
        ->set('mountedActions.0.data.users', [$user->id])
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    roleUsersRelationManagerFor($role)
        ->call('mountTableAction', 'removeFromRole', $user->getKey())
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    expect($user->refresh()->hasRole($role))->toBeTrue()
        ->and($otherUser->refresh()->hasRole($role))->toBeFalse()
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('adds and removes nothing with a wrong password', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();
    $user = createUser();

    roleUsersRelationManagerFor($customRole)
        ->call('mountTableAction', 'addUsers')
        ->set('mountedActions.0.data.users', [$user->id])
        ->set('mountedActions.0.data.current_password', 'wrong-password')
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.current_password']);

    $holder = createUser();
    $holder->assignRole($customRole);

    roleUsersRelationManagerFor($customRole)
        ->call('mountTableAction', 'removeFromRole', $holder->getKey())
        ->set('mountedActions.0.data.current_password', 'wrong-password')
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.current_password']);

    expect($user->refresh()->hasRole($customRole))->toBeFalse()
        ->and($holder->refresh()->hasRole($customRole))->toBeTrue()
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('hides both actions without admin.users.roles and ignores a direct call', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    $customRole = RoleFactory::new()->createOne();
    $holder = createUser();
    $holder->assignRole($customRole);

    $user = createUser();

    $testable = roleUsersRelationManagerFor($customRole);
    $testable->__call('assertTableActionHidden', ['addUsers']);
    $testable->__call('assertTableActionHidden', ['removeFromRole', $holder]);

    roleUsersRelationManagerFor($customRole)
        ->call('mountTableAction', 'addUsers')
        ->set('mountedActions.0.data.users', [$user->id])
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    roleUsersRelationManagerFor($customRole)
        ->call('mountTableAction', 'removeFromRole', $holder->getKey())
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    expect($user->refresh()->hasRole($customRole))->toBeFalse()
        ->and($holder->refresh()->hasRole($customRole))->toBeTrue()
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('tells the role page to refresh after adding or removing users', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();
    $user = createUser();

    roleUsersRelationManagerFor($customRole)
        ->call('mountTableAction', 'addUsers')
        ->set('mountedActions.0.data.users', [$user->id])
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction')
        ->assertDispatched(ViewRole::ROLE_USERS_CHANGED_EVENT);

    roleUsersRelationManagerFor($customRole)
        ->call('mountTableAction', 'removeFromRole', $user->getKey())
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction')
        ->assertDispatched(ViewRole::ROLE_USERS_CHANGED_EVENT);
});

it('shows when and by whom each user received the role, or that it was not recorded', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();
    $user = createUser();
    $grantedUser = createUser();
    $userWithoutGrant = createUser();

    grantRoleWithActivity($customRole, $grantedUser, $user, '2026-10-02 09:15:00');
    $userWithoutGrant->assignRole($customRole);

    $testable = roleUsersRelationManagerFor($customRole)
        ->assertSeeText($user->name)
        ->assertSeeText(TranslatedText::of('admin/roles.relations.users.not_recorded'));
    $testable->__call('assertTableColumnStateSet', ['assigned_by', $user->name, $grantedUser]);
    $testable->__call('assertTableColumnStateSet', ['assigned_by', TranslatedText::of('admin/roles.relations.users.not_recorded'), $userWithoutGrant]);
    $testable->__call('assertTableColumnStateNotSet', ['role_assigned_at', null, $grantedUser]);
});

it('sorts the users by the date they received the role', function (string $direction): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();
    $user = createUser();
    $earlierUser = createUser();
    $laterUser = createUser();

    grantRoleWithActivity($customRole, $laterUser, $user, '2026-10-05 10:00:00');
    grantRoleWithActivity($customRole, $earlierUser, $user, '2026-10-01 10:00:00');

    $testable = roleUsersRelationManagerFor($customRole);
    $testable->__call('sortTable', ['role_assigned_at', $direction]);

    $expectedOrder = [FilamentTableRecords::recordKey($earlierUser), FilamentTableRecords::recordKey($laterUser)];

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), User::class))
        ->toBe($direction === 'asc' ? $expectedOrder : array_reverse($expectedOrder));
})->with(['asc', 'desc']);

it('shows whether each user configured the panel MFA without exposing it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();
    $userWithMfa = createUser();
    $userWithMfa->forceFill([
        'app_authentication_secret' => 'PANELSECRETSENTINEL',
        'app_authentication_recovery_codes' => ['RECOVERYSENTINEL'],
    ])->save();
    $userWithoutMfa = createUser();
    $userWithMfa->assignRole($customRole);
    $userWithoutMfa->assignRole($customRole);

    $testable = roleUsersRelationManagerFor($customRole)
        ->assertDontSee('PANELSECRETSENTINEL')
        ->assertDontSee('RECOVERYSENTINEL');
    $testable->__call('assertTableColumnStateSet', ['panel_mfa', true, $userWithMfa]);
    $testable->__call('assertTableColumnStateSet', ['panel_mfa', false, $userWithoutMfa]);
});

it('lists the other roles of each user without this role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne(['label' => 'Soporte de cuentas']);
    $otherRole = RoleFactory::new()->createOne(['label' => 'Auditor externo']);
    $user = createUser();
    $user->assignRole($customRole);
    $user->assignRole($otherRole);

    $testable = roleUsersRelationManagerFor($customRole);
    $testable->__call('assertTableColumnStateSet', ['other_roles', ['Auditor externo'], $user]);
});

it('runs the same number of queries for 10 and for 50 users', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();
    $otherRole = RoleFactory::new()->createOne();

    foreach (range(1, 50) as $index) {
        $user = createUser();
        grantRoleWithActivity($customRole, $user, createUser(), '2026-10-01 10:00:00');
        $user->assignRole($otherRole);
    }

    countRoleUsersQueries($customRole, 10);

    expect(countRoleUsersQueries($customRole, 50))->toBe(countRoleUsersQueries($customRole, 10));
});
