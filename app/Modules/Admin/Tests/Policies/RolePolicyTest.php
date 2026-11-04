<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Policies\RolePolicy;
use App\User\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Create a user holding the given Admin role.
 */
function createUserWithAdminRole(AdminRole $adminRole): User
{
    $user = createUser();
    $user->assignRole($adminRole->value);

    return $user;
}

function catalogRole(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', 'admin')
        ->sole();
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('resolves RolePolicy for the Spatie role model', function (): void {
    expect(Gate::getPolicyFor(Role::class))->toBeInstanceOf(RolePolicy::class);
});

it('lets the roles manager edit a cataloged role of the admin guard', function (): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesManager);

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', catalogRole(AdminRole::AdminUsersReader)))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', catalogRole(AdminRole::AdminUsersReader)))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', catalogRole(AdminRole::Admin)))->toBeTrue();
});

it('never lets anyone edit a role outside the catalog or of another guard', function (): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesManager);
    $legacyRole = Role::query()->create(['name' => 'legacy-role', 'guard_name' => 'admin']);
    $webRole = Role::query()->create(['name' => AdminRole::AdminUsersReader->value, 'guard_name' => 'web']);

    expect(Gate::forUser($user)->allows('view', $legacyRole))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $legacyRole))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $webRole))->toBeFalse();
});

it('lets the roles reader view roles but not edit them', function (): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesReader);

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', catalogRole(AdminRole::AdminUsersReader)))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', catalogRole(AdminRole::AdminUsersReader)))->toBeFalse();
});

it('denies roles to an administrator without the roles permissions', function (): void {
    $user = createUserWithAdminRole(AdminRole::AdminUsersModerator);

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view', catalogRole(AdminRole::AdminUsersReader)))->toBeFalse();
});

it('denies creating, deleting, restoring and replicating roles even to the roles manager', function (string $ability): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesManager);
    $role = catalogRole(AdminRole::AdminUsersReader);

    expect(Gate::forUser($user)->allows($ability, $role))->toBeFalse()
        ->and(Gate::forUser($user)->allows($ability, Role::class))->toBeFalse();
})->with(['create', 'delete', 'deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny', 'replicate', 'reorder']);
