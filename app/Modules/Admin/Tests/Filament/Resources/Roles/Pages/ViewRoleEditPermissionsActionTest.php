<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

function editableAdminRole(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', 'admin')
        ->sole();
}

/**
 * The cataloged permission names of a role, sorted.
 *
 * @return array<int, mixed>
 */
function editedRolePermissionNames(AdminRole $adminRole): array
{
    return editableAdminRole($adminRole)->permissions->pluck('name')->sort()->values()->all();
}

/**
 * Open the edit permissions modal, select the given permissions and submit it with the password.
 *
 * @param  list<AdminPermission>  $adminPermissions
 * @return Testable<ViewRole>
 */
function submitEditPermissions(Role $role, array $adminPermissions, string $currentPassword = 'password'): Testable
{
    $permissionsByArea = [];

    foreach ($adminPermissions as $adminPermission) {
        $permissionsByArea[$adminPermission->area()][] = $adminPermission->value;
    }

    $testable = Livewire::test(ViewRole::class, ['record' => $role->getKey()])
        ->call('mountAction', 'editPermissions');

    foreach (['access', 'dashboard', 'users', 'posts', 'activity', 'backups', 'announcements', 'roles'] as $area) {
        $testable->set("mountedActions.0.data.permissions.{$area}", $permissionsByArea[$area] ?? []);
    }

    return $testable
        ->set('mountedActions.0.data.current_password', $currentPassword)
        ->call('callMountedAction');
}

/**
 * Open the edit permissions modal and submit the whole selection at once, as a direct call would,
 * without the live dependency handling of each checkbox list.
 *
 * @param  list<AdminPermission>  $adminPermissions
 * @return Testable<ViewRole>
 */
function submitRawEditPermissions(Role $role, array $adminPermissions): Testable
{
    $permissionsByArea = [];

    foreach ($adminPermissions as $adminPermission) {
        $permissionsByArea[$adminPermission->area()][] = $adminPermission->value;
    }

    return Livewire::test(ViewRole::class, ['record' => $role->getKey()])
        ->call('mountAction', 'editPermissions')
        ->set('mountedActions.0.data.permissions', $permissionsByArea)
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');
}

/**
 * The permission names of a role, sorted.
 *
 * @return array<int, mixed>
 */
function storedRolePermissionNames(Role $role): array
{
    return $role->refresh()->permissions->pluck('name')->sort()->values()->all();
}

/**
 * Sign in a roles manager who also holds the base admin role.
 */
function actingAsRolesManager(TestCase $testCase): User
{
    return AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lets a roles manager change the permissions of a role and records each change', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = actingAsRolesManager($testCase);

    submitEditPermissions(editableAdminRole(AdminRole::AdminPostsReader), [
        AdminPermission::AdminPostsViewAny,
        AdminPermission::AdminActivityViewAny,
    ])->assertHasNoErrors();

    Notification::assertNotified(trans_choice('admin/roles.actions.edit_permissions.success', 2, ['count' => 2]));

    $activityLogs = ActivityLog::query()->where('log_name', 'admin')->get();

    expect(editedRolePermissionNames(AdminRole::AdminPostsReader))->toBe([
        AdminPermission::AdminActivityViewAny->value,
        AdminPermission::AdminPostsViewAny->value,
    ])
        ->and($activityLogs->pluck('event')->sort()->values()->all())->toBe([
            ActivityLogAdminEvent::RolePermissionGranted->value,
            ActivityLogAdminEvent::RolePermissionRevoked->value,
        ])
        ->and($activityLogs->pluck('causer_id')->unique()->all())->toBe([$user->id]);
});

it('opens the modal with the current permissions of the role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    Livewire::test(ViewRole::class, ['record' => editableAdminRole(AdminRole::AdminUsersModerator)->getKey()])
        ->call('mountAction', 'editPermissions')
        ->assertSet('mountedActions.0.data.permissions.users', [
            AdminPermission::AdminUsersViewAny->value,
            AdminPermission::AdminUsersView->value,
            AdminPermission::AdminUsersSuspend->value,
            AdminPermission::AdminUsersReactivate->value,
        ])
        ->assertSet('mountedActions.0.data.permissions.posts', []);
});

it('reports when nothing changed and records nothing', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    submitEditPermissions(editableAdminRole(AdminRole::AdminPostsReader), [
        AdminPermission::AdminPostsViewAny,
        AdminPermission::AdminPostsView,
    ])->assertHasNoErrors();

    Notification::assertNotified(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.actions.edit_permissions.unchanged'));

    expect(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('changes nothing with a wrong password', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    submitEditPermissions(editableAdminRole(AdminRole::AdminPostsReader), [AdminPermission::AdminBackupsDownload], 'wrong-password')
        ->assertHasErrors(['mountedActions.0.data.current_password']);

    expect(editedRolePermissionNames(AdminRole::AdminPostsReader))->toBe([
        AdminPermission::AdminPostsView->value,
        AdminPermission::AdminPostsViewAny->value,
    ]);
});

it('requires the current password', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    submitEditPermissions(editableAdminRole(AdminRole::AdminPostsReader), [], '')
        ->assertHasErrors(['mountedActions.0.data.current_password' => 'required']);

    expect(editedRolePermissionNames(AdminRole::AdminPostsReader))->toHaveCount(2);
});

it('lets a roles manager change the permissions of a custom role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);
    $customRole = RoleFactory::new()->createOne();
    $customRole->givePermissionTo(AdminPermission::AdminPostsViewAny->value);

    submitEditPermissions($customRole, [AdminPermission::AdminBackupsView])->assertHasNoErrors();

    expect(storedRolePermissionNames($customRole))->toBe([AdminPermission::AdminBackupsView->value])
        ->and(ActivityLog::query()->where('log_name', 'admin')->count())->toBe(2);
});

it('refuses to remove the last panel access through a custom role and says why', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::AdminRolesManager);
    $gatekeeperRole = RoleFactory::new()->createOne();
    $gatekeeperRole->givePermissionTo(AdminPermission::AdminAccess->value);

    $user->assignRole($gatekeeperRole);

    submitEditPermissions($gatekeeperRole, [AdminPermission::AdminPostsViewAny])->assertHasNoErrors();

    Notification::assertNotified(AdminAuthorizationFailure::LastAdminAccess->message());

    expect(storedRolePermissionNames($gatekeeperRole))->toBe([AdminPermission::AdminAccess->value])
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('does not offer the action on the locked admin role and ignores a direct call', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);
    $adminRole = editableAdminRole(AdminRole::Admin);

    Livewire::test(ViewRole::class, ['record' => $adminRole->getKey()])
        ->assertDontSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.actions.edit_permissions.label'));

    submitEditPermissions($adminRole, [AdminPermission::AdminAccess, AdminPermission::AdminDashboardView]);

    expect(editedRolePermissionNames(AdminRole::Admin))->toBe([AdminPermission::AdminAccess->value])
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('checks the requirements of a permission as soon as it is checked', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    Livewire::test(ViewRole::class, ['record' => editableAdminRole(AdminRole::AdminPostsReader)->getKey()])
        ->call('mountAction', 'editPermissions')
        ->set('mountedActions.0.data.permissions.users', [AdminPermission::AdminUsersSuspend->value])
        ->assertSet('mountedActions.0.data.permissions.users', [
            AdminPermission::AdminUsersViewAny->value,
            AdminPermission::AdminUsersView->value,
            AdminPermission::AdminUsersSuspend->value,
        ])
        ->set('mountedActions.0.data.permissions.posts', [AdminPermission::AdminPostsView->value])
        ->assertSet('mountedActions.0.data.permissions.posts', []);
});

it('summarizes the change before saving', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    $testable = Livewire::test(ViewRole::class, ['record' => editableAdminRole(AdminRole::AdminPostsReader)->getKey()])
        ->call('mountAction', 'editPermissions');

    $testable->__call('assertMountedActionModalSee', [[
        translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.summary.heading'),
        translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.summary.none'),
    ]]);

    $testable->set('mountedActions.0.data.permissions.backups', [AdminPermission::AdminBackupsView->value]);

    $testable->__call('assertMountedActionModalSee', [[
        trans_choice('admin/roles.summary.grant', 1, ['count' => 1, 'list' => AdminPermission::AdminBackupsView->label()]),
    ]]);
});

it('refuses a direct call with a selection that lacks a requirement and says why', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    submitRawEditPermissions(editableAdminRole(AdminRole::AdminPostsReader), [
        AdminPermission::AdminPostsViewAny,
        AdminPermission::AdminPostsView,
        AdminPermission::AdminUsersView,
    ])->assertHasNoErrors();

    Notification::assertNotified(AdminAuthorizationFailure::PermissionDependencyMissing->message());

    expect(editedRolePermissionNames(AdminRole::AdminPostsReader))->toBe([
        AdminPermission::AdminPostsView->value,
        AdminPermission::AdminPostsViewAny->value,
    ])->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('refuses a change that removes the manager own access to this action', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    submitEditPermissions(editableAdminRole(AdminRole::AdminRolesManager), [
        AdminPermission::AdminRolesViewAny,
        AdminPermission::AdminRolesView,
    ]);

    Notification::assertNotified(AdminAuthorizationFailure::ActorWouldLoseAccess->message());

    expect(editedRolePermissionNames(AdminRole::AdminRolesManager))->toHaveCount(8);
});

it('hides the action from a roles reader and ignores a direct call', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    $role = editableAdminRole(AdminRole::AdminPostsReader);

    Livewire::test(ViewRole::class, ['record' => $role->getKey()])
        ->assertDontSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.actions.edit_permissions.label'));

    submitEditPermissions($role, [AdminPermission::AdminBackupsDownload]);

    expect(editedRolePermissionNames(AdminRole::AdminPostsReader))->toHaveCount(2);
});

it('shows the modal in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRolesManager($testCase);

    $testable = Livewire::test(ViewRole::class, ['record' => editableAdminRole(AdminRole::AdminPostsReader)->getKey()])
        ->assertSee('Editar permisos')
        ->call('mountAction', 'editPermissions');

    $testable->__call('assertMountedActionModalSee', [[
        'Editar los permisos de Lector de publicaciones',
        'Publicaciones',
        'Ver una publicación',
        'Abrir el detalle de una publicación y sus comentarios.',
        'Contraseña actual',
        'Guardar permisos',
    ]]);
});
