<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\User\Models\User;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Console\Command\Command;

function runAdminRolePermissions(string $action, string $role, string $permission, ?User $user = null): int
{
    $arguments = [
        'action' => $action,
        'role' => $role,
        'permission' => $permission,
    ];

    if ($user instanceof User) {
        $arguments['--actor'] = $user->email;
    }

    return Artisan::call('admin:role-permissions', $arguments);
}

function adminRoleHasPermission(AdminRole $adminRole, AdminPermission $adminPermission): bool
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->firstOrFail()
        ->hasPermissionTo($adminPermission->value);
}

/**
 * A custom role of the `admin` guard that grants `admin.access`.
 */
function customGatekeeperRole(): Role
{
    $role = RoleFactory::new()->createOne(['name' => 'custom-gatekeeper']);
    $role->givePermissionTo(AdminPermission::AdminAccess->value);

    return $role;
}

it('grants a cataloged permission to a role and updates permission checks', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminPostsReader->value);

    $exitCode = runAdminRolePermissions('grant', AdminRole::AdminPostsReader->value, AdminPermission::AdminDashboardView->value, createAdminCommandActor());
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('Permission admin.dashboard.view is granted to role admin-posts-reader.')
        ->and($user->can(AdminPermission::AdminPostsViewAny->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminDashboardView->value))->toBeTrue()
        ->and(adminRoleHasPermission(AdminRole::AdminPostsReader, AdminPermission::AdminDashboardView))->toBeTrue();
});

it('revokes a cataloged permission without changing role assignments', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    $exitCode = runAdminRolePermissions('revoke', AdminRole::AdminUsersReader->value, AdminPermission::AdminUsersView->value, createAdminCommandActor());
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersView->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminUsersViewAny->value))->toBeTrue();
});

it('uses the admin guard when matching role and permission names exist in web', function (): void {
    $webRole = Role::query()->create([
        'name' => AdminRole::AdminUsersReader->value,
        'guard_name' => 'web',
    ]);
    $webPermission = Permission::query()->create([
        'name' => AdminPermission::AdminDashboardView->value,
        'guard_name' => 'web',
    ]);

    $this->seed(AdminAuthorizationSeeder::class);

    $adminRole = Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $adminPermission = Permission::query()
        ->where('name', AdminPermission::AdminDashboardView->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();

    $exitCode = runAdminRolePermissions('grant', AdminRole::AdminUsersReader->value, AdminPermission::AdminDashboardView->value, createAdminCommandActor());

    $adminRole->refresh();
    $webRole->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($adminRole->permissions()->whereKey($adminPermission->getKey())->exists())->toBeTrue()
        ->and($webRole->permissions()->whereKey($webPermission->getKey())->exists())->toBeFalse();
});

it('rejects an unknown role without changing role permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions('grant', 'unlisted-role', AdminPermission::AdminUsersViewAny->value, createAdminCommandActor());

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Unknown Admin role "unlisted-role".')
        ->and(Role::query()->where('name', 'unlisted-role')->exists())->toBeFalse()
        ->and(adminRoleHasPermission(AdminRole::Admin, AdminPermission::AdminAccess))->toBeTrue()
        ->and(adminRoleHasPermission(AdminRole::Admin, AdminPermission::AdminUsersViewAny))->toBeFalse();
});

it('rejects an unknown permission without changing role permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions('grant', AdminRole::Admin->value, 'unlisted.permission', createAdminCommandActor());

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Unknown Admin permission "unlisted.permission".')
        ->and(adminRoleHasPermission(AdminRole::Admin, AdminPermission::AdminUsersViewAny))->toBeFalse()
        ->and(Permission::query()->where('name', 'unlisted.permission')->exists())->toBeFalse();
});

it('rejects actions other than grant or revoke without changing role permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions('replace', AdminRole::Admin->value, AdminPermission::AdminUsersViewAny->value, createAdminCommandActor());

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Action must be "grant" or "revoke".')
        ->and(adminRoleHasPermission(AdminRole::Admin, AdminPermission::AdminUsersViewAny))->toBeFalse();
});

it('requires the Admin authorization catalog to be seeded', function (): void {
    $exitCode = runAdminRolePermissions('grant', AdminRole::Admin->value, AdminPermission::AdminAccess->value, createUser());

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Admin authorization is not initialized.')
        ->and(Role::query()->exists())->toBeFalse()
        ->and(Permission::query()->exists())->toBeFalse();
});

it('refuses to change the permissions of the locked admin role', function (string $action, AdminPermission $adminPermission): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions($action, AdminRole::Admin->value, $adminPermission->value, createAdminCommandActor());

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('The Admin role admin is locked: its permissions cannot change.')
        ->and(Role::findByName(AdminRole::Admin->value, 'admin')->permissions->pluck('name')->all())->toBe([AdminPermission::AdminAccess->value])
        ->and(Activity::query()->where('log_name', 'admin')->exists())->toBeFalse();
})->with([
    'grant' => ['grant', AdminPermission::AdminDashboardView],
    'revoke' => ['revoke', AdminPermission::AdminAccess],
]);

it('grants and revokes the permissions of a custom role', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
    RoleFactory::new()->createOne(['name' => 'custom-soporte']);
    $user = createUser();
    $user->assignRole('custom-soporte');

    $actor = createAdminCommandActor();

    $grantExitCode = runAdminRolePermissions('grant', 'custom-soporte', AdminPermission::AdminUsersViewAny->value, $actor);
    $userCouldViewUsers = $user->refresh()->can(AdminPermission::AdminUsersViewAny->value);
    $revokeExitCode = runAdminRolePermissions('revoke', 'custom-soporte', AdminPermission::AdminUsersViewAny->value, $actor);

    expect([$grantExitCode, $revokeExitCode])->toBe([Command::SUCCESS, Command::SUCCESS])
        ->and($userCouldViewUsers)->toBeTrue()
        ->and($user->refresh()->can(AdminPermission::AdminUsersViewAny->value))->toBeFalse()
        ->and(Activity::query()->where('log_name', 'admin')->pluck('event')->all())
        ->toBe(['admin.role_permission_granted', 'admin.role_permission_revoked']);
});

it('refuses to grant a permission whose requirements the role lacks', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions('grant', AdminRole::AdminPostsReader->value, AdminPermission::AdminUsersView->value, createAdminCommandActor());

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Permission admin.users.view requires admin.users.viewAny. Grant them to role admin-posts-reader first.')
        ->and(adminRoleHasPermission(AdminRole::AdminPostsReader, AdminPermission::AdminUsersView))->toBeFalse()
        ->and(Activity::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('refuses to revoke a permission that another permission of the role requires', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions('revoke', AdminRole::AdminUsersReader->value, AdminPermission::AdminUsersViewAny->value, createAdminCommandActor());

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Permission admin.users.viewAny is required by admin.users.view on role admin-users-reader. Revoke them first.')
        ->and(adminRoleHasPermission(AdminRole::AdminUsersReader, AdminPermission::AdminUsersViewAny))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('prevents revoking admin.access when it would remove the last panel access', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(customGatekeeperRole());

    $exitCode = runAdminRolePermissions('revoke', 'custom-gatekeeper', AdminPermission::AdminAccess->value, $user);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('would leave no user with panel access')
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('allows revoking admin.access when a user retains it through another role', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $targetUser = createUser();
    $targetUser->assignRole(customGatekeeperRole());

    $user = createAdminCommandActor();

    $exitCode = runAdminRolePermissions('revoke', 'custom-gatekeeper', AdminPermission::AdminAccess->value, $user);
    $targetUser->refresh();
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($targetUser->can(AdminPermission::AdminAccess->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($targetUser->hasRole('custom-gatekeeper'))->toBeTrue();
});

it('records who granted the permission and the role it was granted to', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createAdminCommandActor();

    runAdminRolePermissions('grant', AdminRole::AdminPostsReader->value, AdminPermission::AdminDashboardView->value, $user);

    $activity = Activity::query()->where('event', 'admin.role_permission_granted')->sole();

    expect($activity)
        ->log_name->toBe('admin')
        ->subject_type->toBe('role')
        ->causer_id->toBe($user->id)
        ->and($activity->subject?->getAttribute('name'))->toBe(AdminRole::AdminPostsReader->value)
        ->and($activity->properties?->get('permission'))->toBe(AdminPermission::AdminDashboardView->value);
});

it('records a revoked permission', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createAdminCommandActor();

    runAdminRolePermissions('revoke', AdminRole::AdminUsersReader->value, AdminPermission::AdminUsersView->value, $user);

    expect(Activity::query()->where('event', 'admin.role_permission_revoked')->sole()->causer_id)->toBe($user->id);
});

it('does not record granting a permission the role already has', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions('grant', AdminRole::AdminUsersReader->value, AdminPermission::AdminUsersViewAny->value, createAdminCommandActor());

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(Activity::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('does not record a revocation blocked to protect the last panel access', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(customGatekeeperRole());

    $exitCode = runAdminRolePermissions('revoke', 'custom-gatekeeper', AdminPermission::AdminAccess->value, $user);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Activity::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('rejects the command without --actor and changes nothing', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions('grant', AdminRole::Admin->value, AdminPermission::AdminUsersViewAny->value);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('The --actor option is required')
        ->and(adminRoleHasPermission(AdminRole::Admin, AdminPermission::AdminUsersViewAny))->toBeFalse();
});

it('rejects an actor without admin.access and changes nothing', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminRolePermissions('grant', AdminRole::Admin->value, AdminPermission::AdminUsersViewAny->value, createUser());

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('must have admin.access')
        ->and(adminRoleHasPermission(AdminRole::Admin, AdminPermission::AdminUsersViewAny))->toBeFalse()
        ->and(Activity::query()->exists())->toBeFalse();
});
