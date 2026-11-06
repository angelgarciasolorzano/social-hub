<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleUsersRelationManager;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The users tab of a role with the given users selected.
 *
 * @param  list<User>  $users
 * @return Testable<Component>
 */
function roleUsersTabWithSelection(Role $role, array $users): Testable
{
    $testable = Livewire::test(RoleUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class]);
    $testable->__call('selectTableRecords', [array_map(static fn (User $user): int => $user->id, $users)]);

    return $testable;
}

/**
 * Submit the bulk removal of the role with the given password.
 *
 * @param  Testable<Component>  $testable
 * @return Testable<Component>
 */
function submitBulkRoleRemoval(Testable $testable, string $currentPassword): Testable
{
    $testable->__call('callAction', [
        TestAction::make('removeSelectedFromRole')->table()->bulk(),
        ['current_password' => $currentPassword],
    ]);

    return $testable;
}

/**
 * A new user holding the role.
 */
function bulkRoleHolder(Role $role): User
{
    $user = createUser();
    $user->assignRole($role);

    return $user;
}

/**
 * How many `admin.role_revoked` activities were recorded.
 */
function recordedRoleRevocations(): int
{
    return ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleRevoked->value)->count();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('removes the role from the selected users with one activity each', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $role = RoleFactory::new()->createOne();
    $firstUser = bulkRoleHolder($role);
    $secondUser = bulkRoleHolder($role);
    $untouchedUser = bulkRoleHolder($role);

    $testable = submitBulkRoleRemoval(roleUsersTabWithSelection($role, [$firstUser, $secondUser]), 'password');
    $testable->assertHasNoErrors();
    $testable->assertDispatched(ViewRole::ROLE_USERS_CHANGED_EVENT);

    Notification::assertNotified(trans_choice('admin/roles.relations.users.bulk_remove.success', 2, ['count' => 2]));

    expect($firstUser->refresh()->hasRole($role))->toBeFalse()
        ->and($secondUser->refresh()->hasRole($role))->toBeFalse()
        ->and($untouchedUser->refresh()->hasRole($role))->toBeTrue()
        ->and(recordedRoleRevocations())->toBe(2);
});

it('removes nobody with a wrong password', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $role = RoleFactory::new()->createOne();
    $user = bulkRoleHolder($role);

    submitBulkRoleRemoval(roleUsersTabWithSelection($role, [$user]), 'wrong-password')
        ->assertHasErrors(['mountedActions.0.data.current_password']);

    expect($user->refresh()->hasRole($role))->toBeTrue()
        ->and(recordedRoleRevocations())->toBe(0);
});

it('removes nobody when one of the removals is refused', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::AdminRolesManager);
    $adminRole = Role::query()->where('name', AdminRole::Admin->value)->where('guard_name', Role::GUARD_NAME)->sole();
    $user = bulkRoleHolder($adminRole);
    $lastAdministrator = bulkRoleHolder($adminRole);

    submitBulkRoleRemoval(roleUsersTabWithSelection($adminRole, [$user, $lastAdministrator]), 'password');

    Notification::assertNotified(AdminAuthorizationFailure::LastAdminAccess->message());

    expect($user->refresh()->hasRole($adminRole))->toBeTrue()
        ->and($lastAdministrator->refresh()->hasRole($adminRole))->toBeTrue()
        ->and(recordedRoleRevocations())->toBe(0);
});

it('never lets the administrator select or remove their own account', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    $role = RoleFactory::new()->createOne();
    $user->assignRole($role);
    $otherUser = bulkRoleHolder($role);

    $testable = roleUsersTabWithSelection($role, [$user, $otherUser]);
    $table = FilamentTableRecords::table($testable)->getTable();

    expect($table->isRecordSelectable($user))->toBeFalse()
        ->and($table->isRecordSelectable($otherUser))->toBeTrue();

    submitBulkRoleRemoval($testable, 'password');

    expect($user->refresh()->hasRole($role))->toBeTrue()
        ->and($otherUser->refresh()->hasRole($role))->toBeFalse()
        ->and(recordedRoleRevocations())->toBe(1);
});

it('hides the action without admin.users.roles and ignores a direct call', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    $role = RoleFactory::new()->createOne();
    $user = bulkRoleHolder($role);

    $testable = roleUsersTabWithSelection($role, [$user]);
    $testable->__call('assertActionHidden', [TestAction::make('removeSelectedFromRole')->table()->bulk()]);

    $testable
        ->call('mountTableBulkAction', 'removeSelectedFromRole', [$user->id])
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    expect($user->refresh()->hasRole($role))->toBeTrue()
        ->and(recordedRoleRevocations())->toBe(0);
});
