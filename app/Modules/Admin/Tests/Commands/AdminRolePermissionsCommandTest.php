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
        'permission' => AdminPermission::UsersViewAny->value,
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($user->can(AdminPermission::UsersViewAny->value))->toBeTrue()
        ->and(Role::query()
            ->where('name', AdminRole::Admin->value)
            ->firstOrFail()
            ->hasPermissionTo(AdminPermission::UsersViewAny->value))->toBeTrue();
});

it('revokes a cataloged permission without changing role assignments', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::UsersReader->value);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'revoke',
        'role' => AdminRole::UsersReader->value,
        'permission' => AdminPermission::UsersViewAny->value,
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::UsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::UsersViewAny->value))->toBeFalse()
        ->and($user->can(AdminPermission::UsersView->value))->toBeTrue();
});

it('rejects an unknown role without changing role permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'grant',
        'role' => 'unlisted-role',
        'permission' => AdminPermission::UsersViewAny->value,
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
            ->hasPermissionTo(AdminPermission::UsersViewAny->value))->toBeFalse();
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
            ->hasPermissionTo(AdminPermission::UsersViewAny->value))->toBeFalse()
        ->and(Permission::query()->where('name', 'unlisted.permission')->exists())->toBeFalse();
});

it('rejects actions other than grant or revoke without changing role permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = Artisan::call('admin:role-permissions', [
        'action' => 'replace',
        'role' => AdminRole::Admin->value,
        'permission' => AdminPermission::UsersViewAny->value,
    ]);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Action must be "grant" or "revoke".')
        ->and(Role::query()
            ->where('name', AdminRole::Admin->value)
            ->firstOrFail()
            ->hasPermissionTo(AdminPermission::UsersViewAny->value))->toBeFalse();
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
        ->where('name', AdminRole::UsersReader->value)
        ->firstOrFail()
        ->givePermissionTo(AdminPermission::AdminAccess->value);

    $targetUser = createUser();
    $targetUser->assignRole(AdminRole::Admin->value);

    $user = createUser();
    $user->assignRole(AdminRole::UsersReader->value);

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
