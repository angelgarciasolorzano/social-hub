<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use App\User\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * Create a user holding the account moderation role.
 */
function createUserModerator(): User
{
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersModerator->value);

    return $user;
}

it('resolves UserPolicy from the User model attribute', function (): void {
    $user = createUser();

    expect(Gate::getPolicyFor($user))->toBeInstanceOf(UserPolicy::class);
});

it('allows listing users only with admin.users.viewAny', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $authenticatedUser = createUser();
    grantAdminPermissionThroughTestRole($authenticatedUser, AdminPermission::AdminUsersViewAny);
    $targetUser = createUser();

    expect(Gate::forUser($authenticatedUser)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($authenticatedUser)->allows('view', $targetUser))->toBeFalse();
});

it('allows viewing a user only with admin.users.view', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $authenticatedUser = createUser();
    grantAdminPermissionThroughTestRole($authenticatedUser, AdminPermission::AdminUsersView);
    $targetUser = createUser();

    expect(Gate::forUser($authenticatedUser)->allows('view', $targetUser))->toBeTrue()
        ->and(Gate::forUser($authenticatedUser)->allows('viewAny', User::class))->toBeFalse();
});

it('denies both read abilities when the user has no admin permission', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $authenticatedUser = createUser();
    $targetUser = createUser();

    expect(Gate::forUser($authenticatedUser)->allows('viewAny', User::class))->toBeFalse()
        ->and(Gate::forUser($authenticatedUser)->allows('view', $targetUser))->toBeFalse();
});

it('does not grant user access through posts or dashboard permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $targetUser = createUser();
    $unrelatedPermissions = [
        AdminPermission::AdminPostsViewAny,
        AdminPermission::AdminPostsView,
        AdminPermission::AdminDashboardView,
    ];

    foreach ($unrelatedPermissions as $unrelatedPermission) {
        $authenticatedUser = createUser();
        grantAdminPermissionThroughTestRole($authenticatedUser, $unrelatedPermission);

        expect(Gate::forUser($authenticatedUser)->allows('viewAny', User::class))
            ->toBeFalse()
            ->and(Gate::forUser($authenticatedUser)->allows('view', $targetUser))
            ->toBeFalse();
    }
});

it('denies every user mutation ability directly through the gate', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);
    $targetUser = createUser();

    $writeAbilities = [
        'create' => User::class,
        'update' => $targetUser,
        'delete' => $targetUser,
        'deleteAny' => User::class,
        'restore' => $targetUser,
        'forceDelete' => $targetUser,
    ];

    foreach ($writeAbilities as $ability => $authorizationSubject) {
        expect(Gate::forUser($authenticatedUser)->allows($ability, $authorizationSubject))
            ->toBeFalse();
    }
});

it('allows a moderator to suspend another active account', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    expect(Gate::forUser(createUserModerator())->allows('suspend', createUser()))->toBeTrue();
});

it('allows a moderator to reactivate a suspended account', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    expect(Gate::forUser(createUserModerator())->allows('reactivate', createSuspendedUser()))->toBeTrue();
});

it('denies suspending and reactivating without the moderation permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole([AdminRole::Admin->value, AdminRole::AdminUsersReader->value]);

    expect(Gate::forUser($user)->allows('suspend', createUser()))->toBeFalse()
        ->and(Gate::forUser($user)->allows('reactivate', createSuspendedUser()))->toBeFalse();
});

it('denies a moderator suspending their own account', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUserModerator();

    expect(Gate::forUser($user)->allows('suspend', $user))->toBeFalse();
});

it('denies suspending an account that is already suspended', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    expect(Gate::forUser(createUserModerator())->allows('suspend', createSuspendedUser()))->toBeFalse();
});

it('denies reactivating an account that is active', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    expect(Gate::forUser(createUserModerator())->allows('reactivate', createUser()))->toBeFalse();
});

it('keeps every other user mutation denied for a moderator', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUserModerator();
    $targetUser = createUser();

    expect(Gate::forUser($user)->allows('update', $targetUser))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $targetUser))->toBeFalse()
        ->and(Gate::forUser($user)->allows('forceDelete', $targetUser))->toBeFalse();
});
