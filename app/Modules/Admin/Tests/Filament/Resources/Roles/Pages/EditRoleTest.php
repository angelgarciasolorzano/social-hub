<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\EditRole;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sign in an administrator who may edit roles.
 */
function actingAsRoleEditor(TestCase $testCase): User
{
    return AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
}

/**
 * The edit page of a role.
 *
 * @return Testable<EditRole>
 */
function editRolePage(Role $role): Testable
{
    return Livewire::test(EditRole::class, ['record' => $role->getKey()]);
}

/**
 * A role of the catalog.
 */
function editableCatalogRole(AdminRole $adminRole): Role
{
    return Role::query()->where('name', $adminRole->value)->where('guard_name', Role::GUARD_NAME)->sole();
}

/**
 * The names of the permissions the role holds, sorted.
 *
 * @return list<string>
 */
function editedPermissionNames(Role $role): array
{
    /** @var list<string> $permissionNames */
    $permissionNames = $role->refresh()->permissions->pluck('name')->sort()->values()->all();

    return $permissionNames;
}

/**
 * How many admin activities were recorded.
 */
function recordedAdminActivities(): int
{
    return ActivityLog::query()->where('log_name', 'admin')->count();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('starts from the current details and permissions of the role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleEditor($testCase);
    $role = RoleFactory::new()->createOne(['label' => 'Soporte', 'description' => 'Atiende a los usuarios.']);
    $role->givePermissionTo(AdminPermission::AdminPostsViewAny->value);

    editRolePage($role)
        ->assertSet('data.label', 'Soporte')
        ->assertSet('data.description', 'Atiende a los usuarios.')
        ->assertSet('data.permissions.posts', [AdminPermission::AdminPostsViewAny->value])
        ->assertSet('data.permissions.users', []);
});

it('saves the details and permissions of a custom role together and goes back to it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleEditor($testCase);
    $role = RoleFactory::new()->createOne(['name' => 'custom-soporte', 'label' => 'Soporte']);
    $role->givePermissionTo(AdminPermission::AdminPostsViewAny->value);

    $testable = editRolePage($role)
        ->set('data.label', 'Soporte de cuentas')
        ->set('data.description', 'Atiende a los usuarios.')
        ->set('data.permissions.posts', [])
        ->set('data.permissions.users', [AdminPermission::AdminUsersView->value])
        ->set('data.current_password', 'password')
        ->call('save');

    $testable->assertHasNoErrors();
    $testable->assertRedirect(RoleResource::getUrl('view', ['record' => $role]));
    Notification::assertNotified(TranslatedText::of('admin/roles.actions.edit.success', ['role' => 'Soporte de cuentas']));

    $role->refresh();

    expect($role->name)->toBe('custom-soporte')
        ->and($role->label)->toBe('Soporte de cuentas')
        ->and($role->description)->toBe('Atiende a los usuarios.')
        ->and(editedPermissionNames($role))->toBe([AdminPermission::AdminUsersView->value, AdminPermission::AdminUsersViewAny->value])
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleUpdated->value)->count())->toBe(1)
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RolePermissionGranted->value)->count())->toBe(2)
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RolePermissionRevoked->value)->count())->toBe(1);
});

it('changes nothing when the permissions are refused, not even the details', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    $role = RoleFactory::new()->createOne(['label' => 'Gestión de roles']);
    $role->givePermissionTo([AdminPermission::AdminRolesViewAny->value, AdminPermission::AdminRolesView->value, AdminPermission::AdminRolesUpdate->value]);

    $user->assignRole($role);

    editRolePage($role)
        ->set('data.label', 'Lectura de roles')
        ->set('data.permissions.roles', [AdminPermission::AdminRolesViewAny->value, AdminPermission::AdminRolesView->value])
        ->set('data.current_password', 'password')
        ->call('save');

    Notification::assertNotified(AdminAuthorizationFailure::ActorWouldLoseAccess->message());

    expect($role->refresh()->label)->toBe('Gestión de roles')
        ->and(editedPermissionNames($role))->toHaveCount(3)
        ->and(recordedAdminActivities())->toBe(0);
});

it('reports a taken label on its field and changes nothing', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleEditor($testCase);
    RoleFactory::new()->createOne(['label' => 'Soporte']);
    $role = RoleFactory::new()->createOne(['label' => 'Auditor']);

    editRolePage($role)
        ->set('data.label', 'soporte')
        ->set('data.permissions.posts', [AdminPermission::AdminPostsViewAny->value])
        ->set('data.current_password', 'password')
        ->call('save')
        ->assertHasErrors(['data.label' => AdminAuthorizationFailure::RoleLabelTaken->message()]);

    expect($role->refresh()->label)->toBe('Auditor')
        ->and(editedPermissionNames($role))
        ->toBeEmpty()
        ->and(recordedAdminActivities())->toBe(0);
});

it('changes only the permissions of a system role and ignores a label sent by hand', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleEditor($testCase);
    $role = editableCatalogRole(AdminRole::AdminPostsReader);

    editRolePage($role)
        ->assertSee(TranslatedText::of('admin/roles.form.read_only_details'))
        ->set('data.label', 'Lectores')
        ->set('data.permissions.backups', [AdminPermission::AdminBackupsView->value])
        ->set('data.current_password', 'password')
        ->call('save')
        ->assertHasNoErrors();

    expect($role->refresh()->label)->toBeNull()
        ->and(editedPermissionNames($role))->toContain(AdminPermission::AdminBackupsView->value)
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleUpdated->value)->exists())->toBeFalse();
});

it('says nothing changed when saving the same role and records nothing', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleEditor($testCase);
    $role = RoleFactory::new()->createOne(['label' => 'Soporte', 'description' => null]);

    editRolePage($role)
        ->set('data.current_password', 'password')
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertNotified(TranslatedText::of('admin/roles.actions.edit.unchanged'));

    expect(recordedAdminActivities())->toBe(0);
});

it('changes nothing with a wrong password', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleEditor($testCase);
    $role = RoleFactory::new()->createOne(['label' => 'Soporte']);

    editRolePage($role)
        ->set('data.label', 'Otro nombre')
        ->set('data.current_password', 'wrong-password')
        ->call('save')
        ->assertHasErrors(['data.current_password']);

    expect($role->refresh()->label)->toBe('Soporte')
        ->and(recordedAdminActivities())->toBe(0);
});

it('checks the requirements of a permission as soon as it is checked', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleEditor($testCase);

    editRolePage(editableCatalogRole(AdminRole::AdminPostsReader))
        ->set('data.permissions.users', [AdminPermission::AdminUsersSuspend->value])
        ->assertSet('data.permissions.users', [
            AdminPermission::AdminUsersViewAny->value,
            AdminPermission::AdminUsersView->value,
            AdminPermission::AdminUsersSuspend->value,
        ]);
});

it('denies the page of the locked admin role and to a roles reader', function (string $case): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    if ($case === 'locked admin role') {
        actingAsRoleEditor($testCase);
        $role = editableCatalogRole(AdminRole::Admin);
    } else {
        AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
        $role = RoleFactory::new()->createOne();
    }

    editRolePage($role)->assertForbidden();
})->with(['locked admin role', 'roles reader']);
