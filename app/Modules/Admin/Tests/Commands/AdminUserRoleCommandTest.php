<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command;

it('grants a catalog role and exposes its permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'grant',
        'email' => $user->email,
        'role' => AdminRole::UsersReader->value,
    ]);
    $commandOutput = Artisan::output();
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($commandOutput)->toContain("Role users-reader is assigned to {$user->email}.")
        ->and($user->hasRole(AdminRole::UsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::UsersViewAny->value))->toBeTrue()
        ->and($user->can(AdminPermission::UsersView->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeFalse();
});

it('revokes only the selected role and its permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::UsersReader->value);

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'revoke',
        'email' => $user->email,
        'role' => AdminRole::UsersReader->value,
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole(AdminRole::UsersReader->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($user->can(AdminPermission::UsersViewAny->value))->toBeFalse();
});

it('rejects an unknown role without changing existing assignments', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'grant',
        'email' => $user->email,
        'role' => 'unlisted-role',
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Unknown Admin role "unlisted-role".')
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole('unlisted-role'))->toBeFalse()
        ->and(Role::query()->where('name', 'unlisted-role')->exists())->toBeFalse();
});

it('rejects actions other than grant or revoke without mutation', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'replace',
        'email' => $user->email,
        'role' => AdminRole::Admin->value,
    ]);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Action must be "grant" or "revoke".')
        ->and($user->roles()->exists())->toBeFalse();
});

it('rejects an email that does not belong to an existing user', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'grant',
        'email' => 'missing@example.com',
        'role' => AdminRole::Admin->value,
    ]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('No user was found with the email missing@example.com.')
        ->and(User::query()->permission(AdminPermission::AdminAccess->value)->exists())->toBeFalse();
});

it('prevents revoking admin.access from the last administrator', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::UsersReader->value);

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'revoke',
        'email' => $user->email,
        'role' => AdminRole::Admin->value,
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('would leave no user with admin.access')
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole(AdminRole::UsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('allows revoking admin.access from one user when another administrator remains', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $targetUser = createUser();
    $targetUser->assignRole(AdminRole::Admin->value, AdminRole::UsersReader->value);

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'revoke',
        'email' => $targetUser->email,
        'role' => AdminRole::Admin->value,
    ]);
    $user->refresh();
    $targetUser->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($targetUser->hasRole(AdminRole::Admin->value))->toBeFalse()
        ->and($targetUser->hasRole(AdminRole::UsersReader->value))->toBeTrue()
        ->and($targetUser->can(AdminPermission::AdminAccess->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('allows revoking one role when the user retains admin.access through another role', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Role::query()
        ->where('name', AdminRole::UsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail()
        ->givePermissionTo(AdminPermission::AdminAccess->value);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::UsersReader->value);

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'revoke',
        'email' => $user->email,
        'role' => AdminRole::Admin->value,
    ]);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeFalse()
        ->and($user->hasRole(AdminRole::UsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('rejects role changes until the authorization catalog is seeded', function (): void {
    $user = createUser();

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'grant',
        'email' => $user->email,
        'role' => AdminRole::Admin->value,
    ]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Admin authorization is not initialized.')
        ->and($user->roles()->exists())->toBeFalse();
});
