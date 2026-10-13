<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Filament\Panel;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command;

function runAdminUserRole(string $action, string $email, string $role): int
{
    return Artisan::call('admin:user-role', [
        'action' => $action,
        'email' => $email,
        'role' => $role,
    ]);
}

it('grants a catalog role and exposes its permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, AdminRole::AdminUsersReader->value);
    $commandOutput = Artisan::output();
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($commandOutput)->toContain("Role admin-users-reader is assigned to {$user->email}.")
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersViewAny->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersView->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeFalse()
        ->and($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('grants Posts and Dashboard reader roles without panel entry', function (AdminRole $adminRole, AdminPermission $adminPermission): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, $adminRole->value);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole($adminRole->value))->toBeTrue()
        ->and($user->can($adminPermission->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeFalse()
        ->and($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
})->with([
    'Posts reader' => [AdminRole::AdminPostsReader, AdminPermission::AdminPostsViewAny],
    'Dashboard reader' => [AdminRole::AdminDashboardReader, AdminPermission::AdminDashboardView],
]);

it('grants panel entry through the admin role', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, AdminRole::Admin->value);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($user->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
});

it('revokes only the selected role and its permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);

    $exitCode = runAdminUserRole('revoke', $user->email, AdminRole::AdminUsersReader->value);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersViewAny->value))->toBeFalse();
});

it('revokes Posts and Dashboard reader roles without changing panel access', function (AdminRole $adminRole, AdminPermission $adminPermission): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, $adminRole->value);

    $exitCode = runAdminUserRole('revoke', $user->email, $adminRole->value);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole($adminRole->value))->toBeFalse()
        ->and($user->can($adminPermission->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
})->with([
    'Posts reader' => [AdminRole::AdminPostsReader, AdminPermission::AdminPostsView],
    'Dashboard reader' => [AdminRole::AdminDashboardReader, AdminPermission::AdminDashboardView],
]);

it('rejects an unknown role without changing existing assignments', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $exitCode = runAdminUserRole('grant', $user->email, 'unlisted-role');
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

    $exitCode = runAdminUserRole('replace', $user->email, AdminRole::Admin->value);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Action must be "grant" or "revoke".')
        ->and($user->roles()->exists())->toBeFalse();
});

it('rejects an email that does not belong to an existing user', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminUserRole('grant', 'missing@example.com', AdminRole::Admin->value);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('No user was found with the email missing@example.com.')
        ->and(User::query()->permission(AdminPermission::AdminAccess->value)->exists())->toBeFalse();
});

it('prevents revoking admin.access from the last administrator', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);

    $exitCode = runAdminUserRole('revoke', $user->email, AdminRole::Admin->value);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('would leave no user with admin.access')
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('allows revoking admin.access from one user when another administrator remains', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $targetUser = createUser();
    $targetUser->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);

    $exitCode = runAdminUserRole('revoke', $targetUser->email, AdminRole::Admin->value);
    $user->refresh();
    $targetUser->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($targetUser->hasRole(AdminRole::Admin->value))->toBeFalse()
        ->and($targetUser->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($targetUser->can(AdminPermission::AdminAccess->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('allows revoking one role when the user retains admin.access through another role', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail()
        ->givePermissionTo(AdminPermission::AdminAccess->value);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);

    $exitCode = runAdminUserRole('revoke', $user->email, AdminRole::Admin->value);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeFalse()
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('rejects role changes until the authorization catalog is seeded', function (): void {
    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, AdminRole::Admin->value);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Admin authorization is not initialized.')
        ->and($user->roles()->exists())->toBeFalse();
});
