<?php

declare(strict_types=1);

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
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Filament\Actions\Action;
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

it('offers adding and removing users, but no create, edit or bulk actions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    $table = FilamentTableRecords::table(roleUsersRelationManagerFor(roleWithUsers(AdminRole::AdminPostsReader)))->getTable();

    expect(tableActionNames($table->getHeaderActions()))->toBe(['addUsers'])
        ->and(tableActionNames($table->getRecordActions()))->toBe(['removeFromRole'])
        ->and($table->getToolbarActions())->toBeEmpty();
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
        ->call('callMountedAction');

    expect($user->refresh()->hasRole($role))->toBeTrue()
        ->and($otherUser->refresh()->hasRole($role))->toBeFalse()
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('changes nothing with a wrong password', function (): void {
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

    expect($user->refresh()->hasRole($customRole))->toBeFalse();
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
        ->call('callMountedAction')
        ->assertDispatched(ViewRole::ROLE_USERS_CHANGED_EVENT);
});
