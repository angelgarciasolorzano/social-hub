<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

function lifecycleSystemRole(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', Role::GUARD_NAME)
        ->sole();
}

/**
 * @return Testable<ViewRole>
 */
function lifecycleRolePage(Role $role): Testable
{
    return Livewire::test(ViewRole::class, ['record' => $role->getKey()]);
}

/**
 * Sign in an administrator holding the base admin role and the roles manager role.
 */
function actingAsLifecycleRolesManager(TestCase $testCase): User
{
    return AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('links the edit button to the edit page of custom and system roles', function (string $roleType): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);
    $role = $roleType === 'custom' ? RoleFactory::new()->createOne() : lifecycleSystemRole(AdminRole::AdminPostsReader);

    $testable = lifecycleRolePage($role);
    $testable->__call('assertActionVisible', ['edit']);
    $testable->__call('assertActionHasUrl', ['edit', RoleResource::getUrl('edit', ['record' => $role])]);
})->with(['custom', 'system']);

it('does not offer editing on the locked admin role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);

    lifecycleRolePage(lifecycleSystemRole(AdminRole::Admin))->__call('assertActionHidden', ['edit']);
});

it('does not offer deleting a system role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);

    $testable = lifecycleRolePage(lifecycleSystemRole(AdminRole::AdminPostsReader));
    $testable->__call('assertActionHidden', ['delete']);
    $testable->__call('assertActionVisible', ['duplicate']);
});

it('duplicates a system role as a custom role with its permissions and opens it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);
    $sourceRole = lifecycleSystemRole(AdminRole::AdminPostsReader);

    $testable = lifecycleRolePage($sourceRole)
        ->call('mountAction', 'duplicate')
        ->assertSet('mountedActions.0.data.label', TranslatedText::of('admin/roles.actions.duplicate.copy_label', [
            'role' => $sourceRole->displayName(),
        ]))
        ->set('mountedActions.0.data.label', 'Lectores de soporte')
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    $copy = Role::query()->custom()->sole();

    $testable->assertHasNoErrors();
    $testable->assertRedirect(RoleResource::getUrl('view', ['record' => $copy]));

    expect($copy->label)->toBe('Lectores de soporte')
        ->and($copy->permissions->pluck('name')->sort()->values()->all())->toBe([
            AdminPermission::AdminPostsView->value,
            AdminPermission::AdminPostsViewAny->value,
        ]);
});

it('deletes a custom role without users and goes back to the list', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);
    $role = RoleFactory::new()->createOne();

    $testable = lifecycleRolePage($role)
        ->call('mountAction', 'delete')
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    $testable->assertHasNoErrors();
    $testable->assertRedirect(RoleResource::getUrl('index'));

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleDeleted->value)->count())->toBe(1);
});

it('disables deleting a custom role that has users and explains why', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);
    $role = RoleFactory::new()->createOne();
    createUser()->assignRole($role);

    $testable = lifecycleRolePage($role);
    $testable->__call('assertActionDisabled', ['delete']);
    $testable->assertSee(trans_choice('admin/roles.actions.delete.blocked_by_users', 1, ['count' => 1]));

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('ignores a delete submitted after the role gained users', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);
    $role = RoleFactory::new()->createOne();

    $testable = lifecycleRolePage($role)
        ->call('mountAction', 'delete')
        ->set('mountedActions.0.data.current_password', 'password');

    createUser()->assignRole($role);

    $testable->call('callMountedAction');

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleDeleted->value)->exists())->toBeFalse();
});

it('writes nothing with a wrong password', function (string $actionName): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);
    $role = RoleFactory::new()->createOne();

    lifecycleRolePage($role)
        ->call('mountAction', $actionName)
        ->set('mountedActions.0.data.current_password', 'wrong-password')
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.current_password']);

    expect(Role::query()->count())->toBe(count(AdminRole::cases()) + 1)
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
})->with(['duplicate', 'delete']);

it('hides the lifecycle actions from a roles reader', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = RoleFactory::new()->createOne();

    $testable = lifecycleRolePage($role);
    $testable->__call('assertActionHidden', ['edit']);
    $testable->__call('assertActionHidden', ['duplicate']);
    $testable->__call('assertActionHidden', ['delete']);

    $testable
        ->call('mountAction', 'delete')
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('updates its user count and the delete action when the users tab changes the holders', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsLifecycleRolesManager($testCase);
    $role = RoleFactory::new()->createOne();

    $testable = lifecycleRolePage($role);
    $testable->__call('assertActionEnabled', ['delete']);

    createUser()->assignRole($role);

    $testable->dispatch(ViewRole::ROLE_USERS_CHANGED_EVENT);
    $testable->__call('assertActionDisabled', ['delete']);
    $testable->assertSee(trans_choice('admin/roles.actions.delete.blocked_by_users', 1, ['count' => 1]));
});
