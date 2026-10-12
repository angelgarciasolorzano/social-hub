<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command;

it('grants a cataloged permission to a role and updates permission checks', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'grant',
        'role' => AdminRole::Admin->value,
        'permission' => AdminPermission::AdminUsersViewAny->value,
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersViewAny->value))->toBeTrue()
        ->and(Role::query()
            ->where('name', AdminRole::Admin->value)
            ->firstOrFail()
            ->hasPermissionTo(AdminPermission::AdminUsersViewAny->value))->toBeTrue();
});

it('revokes a cataloged permission without changing role assignments', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'revoke',
        'role' => AdminRole::AdminUsersReader->value,
        'permission' => AdminPermission::AdminUsersViewAny->value,
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersViewAny->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminUsersView->value))->toBeTrue();
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

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'grant',
        'role' => AdminRole::AdminUsersReader->value,
        'permission' => AdminPermission::AdminDashboardView->value,
    ]);

    $adminRole->refresh();
    $webRole->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($adminRole->permissions()->whereKey($adminPermission->getKey())->exists())->toBeTrue()
        ->and($webRole->permissions()->whereKey($webPermission->getKey())->exists())->toBeFalse();
});

it('rejects an unknown role without changing role permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'grant',
        'role' => 'unlisted-role',
        'permission' => AdminPermission::AdminUsersViewAny->value,
    ]);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Unknown Admin role "unlisted-role".')
        ->and(Role::query()->where('name', 'unlisted-role')->exists())->toBeFalse()
        ->and(Role::query()
            ->where('name', AdminRole::Admin->value)
            ->firstOrFail()
            ->hasPermissionTo(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and(Role::query()
            ->where('name', AdminRole::Admin->value)
            ->firstOrFail()
            ->hasPermissionTo(AdminPermission::AdminUsersViewAny->value))->toBeFalse();
});

it('rejects an unknown permission without changing role permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'grant',
        'role' => AdminRole::Admin->value,
        'permission' => 'unlisted.permission',
    ]);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Unknown Admin permission "unlisted.permission".')
        ->and(Role::query()
            ->where('name', AdminRole::Admin->value)
            ->firstOrFail()
            ->hasPermissionTo(AdminPermission::AdminUsersViewAny->value))->toBeFalse()
        ->and(Permission::query()->where('name', 'unlisted.permission')->exists())->toBeFalse();
});

it('rejects actions other than grant or revoke without changing role permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'replace',
        'role' => AdminRole::Admin->value,
        'permission' => AdminPermission::AdminUsersViewAny->value,
    ]);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Action must be "grant" or "revoke".')
        ->and(Role::query()
            ->where('name', AdminRole::Admin->value)
            ->firstOrFail()
            ->hasPermissionTo(AdminPermission::AdminUsersViewAny->value))->toBeFalse();
});

it('requires the Admin authorization catalog to be seeded', function (): void {
    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'grant',
        'role' => AdminRole::Admin->value,
        'permission' => AdminPermission::AdminAccess->value,
    ]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Admin authorization is not initialized.')
        ->and(Role::query()->exists())->toBeFalse()
        ->and(Permission::query()->exists())->toBeFalse();
});

it('prevents revoking admin.access when it would remove the last panel access', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'revoke',
        'role' => AdminRole::Admin->value,
        'permission' => AdminPermission::AdminAccess->value,
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('would leave no user with panel access')
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and(Role::query()
            ->where('name', AdminRole::Admin->value)
            ->firstOrFail()
            ->hasPermissionTo(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('allows revoking admin.access when a user retains it through another role', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->firstOrFail()
        ->givePermissionTo(AdminPermission::AdminAccess->value);

    $targetUser = createUser();
    $targetUser->assignRole(AdminRole::Admin->value);

    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'revoke',
        'role' => AdminRole::Admin->value,
        'permission' => AdminPermission::AdminAccess->value,
    ]);
    $targetUser->refresh();
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($targetUser->can(AdminPermission::AdminAccess->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($targetUser->hasRole(AdminRole::Admin->value))->toBeTrue();
});
