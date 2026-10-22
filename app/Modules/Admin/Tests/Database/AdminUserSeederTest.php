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
        AdminPermission::AdminActivityViewAny->value,
        AdminPermission::AdminActivityView->value,
    ];

    $expectedRoleNames = [
        AdminRole::Admin->value,
        AdminRole::AdminDashboardReader->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
        AdminRole::AdminActivityReader->value,
        $customAdminRole->name,
    ];

    expect($user->getRoleNames()->sort()->values()->all())
        ->toBe(collect($expectedRoleNames)->sort()->values()->all())
        ->and($user->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect($expectedPermissionNames)->sort()->values()->all());
});

it('does not assign an existing provisioner role to Angel automatically', function (): void {
    $user = createUser();
    $user->forceFill(['email' => 'angel@gmail.com'])->save();

    $this->seed(AdminAuthorizationSeeder::class);

    $provisionerRole = Role::query()->firstOrCreate([
        'name' => 'admin-users-provisioner',
        'guard_name' => 'admin',
    ]);

    $this->seed(AdminUserSeeder::class);

    expect($user->refresh()->getRoleNames()->all())
        ->not->toContain($provisionerRole->name);
});

it('preserves the roles that other users already have', function (): void {
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

    expect($limitedAdmin->refresh()->hasAllRoles([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]))->toBeTrue()
        ->and($reader->refresh()->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue();
});

it('grants the complete admin permission set to Angel', function (): void {
    $user = createUser();
    $user->forceFill(['email' => 'angel@gmail.com'])->save();

    $this->seed(AdminAuthorizationSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect($user->refresh()->hasAllPermissions([
        AdminPermission::AdminAccess->value,
        AdminPermission::AdminDashboardView->value,
        AdminPermission::AdminPostsView->value,
        AdminPermission::AdminPostsViewAny->value,
        AdminPermission::AdminUsersView->value,
        AdminPermission::AdminUsersViewAny->value,
        AdminPermission::AdminActivityViewAny->value,
        AdminPermission::AdminActivityView->value,
    ]))->toBeTrue();
});
