<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
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

it('resolves RolePolicy for the role model', function (): void {
    expect(Gate::getPolicyFor(Role::class))->toBeInstanceOf(RolePolicy::class);
});

it('lets the roles manager edit the permissions of system and custom roles, but not of admin', function (): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', catalogRole(AdminRole::AdminUsersReader)))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', catalogRole(AdminRole::AdminPostsReader)))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $customRole))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', catalogRole(AdminRole::Admin)))->toBeFalse();
});

it('lets the roles manager rename and delete custom roles only', function (): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();
    $systemRole = catalogRole(AdminRole::AdminPostsReader);

    expect(Gate::forUser($user)->allows('updateDetails', $customRole))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $customRole))->toBeTrue()
        ->and(Gate::forUser($user)->allows('updateDetails', $systemRole))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $systemRole))->toBeFalse()
        ->and(Gate::forUser($user)->allows('updateDetails', catalogRole(AdminRole::Admin)))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', catalogRole(AdminRole::Admin)))->toBeFalse();
});

it('lets the roles manager create roles and duplicate system and custom ones', function (): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesManager);

    expect(Gate::forUser($user)->allows('create', Role::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('replicate', catalogRole(AdminRole::AdminPostsReader)))->toBeTrue()
        ->and(Gate::forUser($user)->allows('replicate', RoleFactory::new()->createOne()))->toBeTrue();
});

it('never lets anyone change a role of another guard', function (string $ability): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesManager);
    $webRole = RoleFactory::new()->createOne(['name' => 'custom-web-role', 'guard_name' => 'web']);

    expect(Gate::forUser($user)->allows($ability, $webRole))->toBeFalse();
})->with(['update', 'updateDetails', 'delete', 'replicate']);

it('ties each write to its own permission', function (AdminPermission $adminPermission, array $expectedAbilities): void {
    $user = createUser();
    grantAdminPermissionThroughTestRole($user, $adminPermission);
    $customRole = RoleFactory::new()->createOne();

    $allowedAbilities = array_values(array_filter(
        ['create', 'replicate', 'update', 'updateDetails', 'delete'],
        static fn (string $ability): bool => Gate::forUser($user)->allows($ability, $ability === 'create' ? Role::class : $customRole),
    ));

    expect($allowedAbilities)->toBe($expectedAbilities);
})->with([
    'create' => [AdminPermission::AdminRolesCreate, ['create', 'replicate']],
    'update' => [AdminPermission::AdminRolesUpdate, ['update', 'updateDetails']],
    'delete' => [AdminPermission::AdminRolesDelete, ['delete']],
]);

it('lets the roles reader view roles but not create, duplicate, edit or delete them', function (string $ability): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesReader);
    $customRole = RoleFactory::new()->createOne();

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $customRole))->toBeTrue()
        ->and(Gate::forUser($user)->allows($ability, $ability === 'create' ? Role::class : $customRole))->toBeFalse();
})->with(['create', 'replicate', 'update', 'updateDetails', 'delete']);

it('denies roles to an administrator without the roles permissions', function (): void {
    $user = createUserWithAdminRole(AdminRole::AdminUsersModerator);

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view', catalogRole(AdminRole::AdminUsersReader)))->toBeFalse();
});

it('denies bulk deleting, restoring, force deleting and reordering roles even to the roles manager', function (string $ability): void {
    $user = createUserWithAdminRole(AdminRole::AdminRolesManager);
    $customRole = RoleFactory::new()->createOne();

    expect(Gate::forUser($user)->allows($ability, $customRole))->toBeFalse()
        ->and(Gate::forUser($user)->allows($ability, Role::class))->toBeFalse();
})->with(['deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny', 'reorder']);
