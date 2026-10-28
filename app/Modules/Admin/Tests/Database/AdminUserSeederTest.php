<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Database\Seeders\AdminUserSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

it('assigns Angel backup operations, user moderation and all reader roles while preserving existing assignments', function (): void {
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
        AdminPermission::AdminBackupsView->value,
        AdminPermission::AdminBackupsCreate->value,
        AdminPermission::AdminBackupsDownload->value,
        AdminPermission::AdminUsersSuspend->value,
        AdminPermission::AdminUsersReactivate->value,
        AdminPermission::AdminAnnouncementsViewAny->value,
        AdminPermission::AdminAnnouncementsView->value,
        AdminPermission::AdminAnnouncementsCreate->value,
        AdminPermission::AdminAnnouncementsUpdate->value,
        AdminPermission::AdminAnnouncementsDelete->value,
    ];

    $expectedRoleNames = [
        AdminRole::Admin->value,
        AdminRole::AdminDashboardReader->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
        AdminRole::AdminActivityReader->value,
        AdminRole::AdminBackupsReader->value,
        AdminRole::AdminBackupsOperator->value,
        AdminRole::AdminUsersModerator->value,
        AdminRole::AdminAnnouncementsManager->value,
        $customAdminRole->name,
    ];

    expect($user->getRoleNames()->sort()->values()->all())
        ->toBe(collect($expectedRoleNames)->sort()->values()->all())
        ->and($user->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect($expectedPermissionNames)->sort()->values()->all());
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
