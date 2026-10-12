<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Database\Seeders\AdminUserSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

it('assigns Angel all reader roles and preserves existing assignments without random promotion', function (): void {
    $angel = createUser();
    $angel->forceFill(['email' => 'angel@gmail.com'])->save();

    $limitedAdmin = createUser();
    $reader = createUser();

    /** @var Factory<User> $userFactory */
    $userFactory = User::factory();
    $userFactory->count(28)->create();

    $this->seed(AdminAuthorizationSeeder::class);
    $customAdminRole = Role::query()->create([
        'name' => 'admin-auditor',
        'guard_name' => 'admin',
    ]);
    $angel->assignRole($customAdminRole);
    $limitedAdmin->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);
    $reader->assignRole(AdminRole::AdminUsersReader->value);

    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $angel->refresh();
    $limitedAdmin->refresh();
    $reader->refresh();

    $expectedPermissionNames = [
        AdminPermission::AdminAccess->value,
        AdminPermission::AdminDashboardView->value,
        AdminPermission::AdminPostsView->value,
        AdminPermission::AdminPostsViewAny->value,
        AdminPermission::AdminUsersView->value,
        AdminPermission::AdminUsersViewAny->value,
    ];

    $usersWithAdminRoles = User::query()
        ->role([
            AdminRole::Admin->value,
            AdminRole::AdminDashboardReader->value,
            AdminRole::AdminUsersReader->value,
            AdminRole::AdminPostsReader->value,
        ], 'admin')
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $expectedUsersWithAdminRoles = collect([
        $angel->getKey(),
        $limitedAdmin->getKey(),
        $reader->getKey(),
    ])->sort()->values()->all();

    $otherPanelAdmins = User::query()
        ->role(AdminRole::Admin->value, 'admin')
        ->whereKeyNot($angel->getKey())
        ->get();

    $usersWithEveryAdminPermission = User::query()
        ->get()
        ->filter(fn (User $user): bool => $user->hasAllPermissions($expectedPermissionNames));

    expect($angel->hasAllRoles([
        AdminRole::Admin->value,
        AdminRole::AdminDashboardReader->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]))->toBeTrue()
        ->and($angel->hasRole('admin-auditor'))->toBeTrue()
        ->and($angel->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect($expectedPermissionNames)->sort()->values()->all())
        ->and($usersWithAdminRoles)->toBe($expectedUsersWithAdminRoles)
        ->and($otherPanelAdmins->modelKeys())->toBe([$limitedAdmin->getKey()])
        ->and($usersWithEveryAdminPermission)->toHaveCount(1)
        ->and($limitedAdmin->hasAllRoles([
            AdminRole::Admin->value,
            AdminRole::AdminUsersReader->value,
        ]))->toBeTrue()
        ->and($limitedAdmin->hasAllPermissions([AdminPermission::AdminAccess->value]))->toBeTrue()
        ->and($reader->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($reader->hasRole(AdminRole::Admin->value))->toBeFalse();
});
