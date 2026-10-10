<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use App\User\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

it('resolves UserPolicy from the User model attribute', function (): void {
    $user = createUser();

    expect(Gate::getPolicyFor($user))->toBeInstanceOf(UserPolicy::class);
});

it('allows listing users only with users.viewAny', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $authenticatedUser = createUser();
    $listUsersRole = Role::findOrCreate('users-list-test', 'web');
    $listUsersRole->givePermissionTo(AdminPermission::UsersViewAny->value);

    $authenticatedUser->assignRole($listUsersRole);
    $targetUser = createUser();

    expect(Gate::forUser($authenticatedUser)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($authenticatedUser)->allows('view', $targetUser))->toBeFalse();
});

it('allows viewing a user only with users.view', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $authenticatedUser = createUser();
    $viewUsersRole = Role::findOrCreate('users-view-test', 'web');
    $viewUsersRole->givePermissionTo(AdminPermission::UsersView->value);

    $authenticatedUser->assignRole($viewUsersRole);
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

it('denies every user mutation ability directly through the gate', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
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
