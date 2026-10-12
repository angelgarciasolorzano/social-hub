<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Database\Seeders\AdminUserSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

it('assigns Angel all reader roles and preserves existing assignments', function (): void {
    $user = createUser();
    $user->forceFill(['email' => 'angel@gmail.com'])->save();

    $this->seed(AdminAuthorizationSeeder::class);

    $customAdminRole = Role::query()->create([
        'name' => 'admin-auditor',
        'guard_name' => 'admin',
    ]);

    $user->assignRole($customAdminRole);

    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $user->refresh();

    $expectedPermissionNames = [
        AdminPermission::AdminAccess->value,
        AdminPermission::AdminDashboardView->value,
        AdminPermission::AdminPostsView->value,
        AdminPermission::AdminPostsViewAny->value,
        AdminPermission::AdminUsersView->value,
        AdminPermission::AdminUsersViewAny->value,
    ];

    $expectedRoleNames = [
        AdminRole::Admin->value,
        AdminRole::AdminDashboardReader->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
        $customAdminRole->name,
    ];

    expect($user->getRoleNames()->sort()->values()->all())
        ->toBe(collect($expectedRoleNames)->sort()->values()->all())
        ->and($user->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect($expectedPermissionNames)->sort()->values()->all());
});

it('preserves limited assignments and does not randomly promote other users', function (): void {
    $user = createUser();
    $user->forceFill(['email' => 'angel@gmail.com'])->save();
    $limitedAdmin = createUser();
    $reader = createUser();

    /** @var Factory<User> $userFactory */
    $userFactory = User::factory();

    $userFactory->count(28)->create();

    $this->seed(AdminAuthorizationSeeder::class);

    $limitedAdmin->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);
    $reader->assignRole(AdminRole::AdminUsersReader->value);

    $this->seed(AdminUserSeeder::class);

    $limitedAdmin->refresh();
    $reader->refresh();

    expect(User::query()->role([
        AdminRole::Admin->value,
        AdminRole::AdminDashboardReader->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ], 'admin')->count())
        ->toBe(3)
        ->and($limitedAdmin->hasAllRoles([
            AdminRole::Admin->value,
            AdminRole::AdminUsersReader->value,
        ]))->toBeTrue()
        ->and($limitedAdmin->hasAllPermissions([AdminPermission::AdminAccess->value]))->toBeTrue()
        ->and($reader->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($reader->hasRole(AdminRole::Admin->value))->toBeFalse();
});

it('grants the complete admin permission set only to Angel', function (): void {
    $user = createUser();
    $user->forceFill(['email' => 'angel@gmail.com'])->save();

    /** @var Factory<User> $userFactory */
    $userFactory = User::factory();
    $userFactory->count(28)->create();

    $this->seed(AdminAuthorizationSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $expectedPermissionNames = [
        AdminPermission::AdminAccess->value,
        AdminPermission::AdminDashboardView->value,
        AdminPermission::AdminPostsView->value,
        AdminPermission::AdminPostsViewAny->value,
        AdminPermission::AdminUsersView->value,
        AdminPermission::AdminUsersViewAny->value,
    ];

    $usersWithAllAdminPermissions = [];

    foreach (User::query()->get() as $candidate) {
        if ($candidate->hasAllPermissions($expectedPermissionNames)) {
            $usersWithAllAdminPermissions[] = $candidate->getKey();
        }
    }

    expect($usersWithAllAdminPermissions)->toBe([$user->getKey()]);
});
