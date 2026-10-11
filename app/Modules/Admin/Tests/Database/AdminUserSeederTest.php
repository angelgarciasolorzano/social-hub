<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Database\Seeders\AdminUserSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

it('gives Angel every admin permission and a random ten percent panel-only access', function (): void {
    $angel = createUser();
    $angel->forceFill(['email' => 'angel@gmail.com'])->save();

    $limitedAdmin = createUser();
    $reader = createUser();

    /** @var Factory<User> $userFactory */
    $userFactory = User::factory();
    $userFactory->count(28)->create();

    $this->seed(AdminAuthorizationSeeder::class);
    $limitedAdmin->assignRole(AdminRole::Admin->value, AdminRole::UsersReader->value);
    $reader->assignRole(AdminRole::UsersReader->value);

    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $angel->refresh();
    $limitedAdmin->refresh();
    $reader->refresh();

    $expectedPermissionNames = [
        AdminPermission::AdminAccess->value,
        AdminPermission::UsersView->value,
        AdminPermission::UsersViewAny->value,
    ];

    $otherPanelAdmins = User::query()
        ->role(AdminRole::Admin->value, 'admin')
        ->where('email', '!=', 'angel@gmail.com')
        ->get();

    $usersWithEveryAdminPermission = User::query()
        ->get()
        ->filter(fn (User $user): bool => $user->hasAllPermissions($expectedPermissionNames));

    expect($angel->hasAllRoles([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]))->toBeTrue()
        ->and($angel->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect($expectedPermissionNames)->sort()->values()->all())
        ->and($otherPanelAdmins)->toHaveCount(3)
        ->and($otherPanelAdmins->every(fn (User $user): bool => $user->getRoleNames()->all() === [AdminRole::Admin->value]
            && $user->getAllPermissions()->pluck('name')->all() === [AdminPermission::AdminAccess->value]))
        ->toBeTrue()
        ->and($usersWithEveryAdminPermission)->toHaveCount(1)
        ->and($limitedAdmin->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($limitedAdmin->hasRole(AdminRole::UsersReader->value))->toBeFalse()
        ->and($limitedAdmin->hasAllPermissions([AdminPermission::AdminAccess->value]))->toBeTrue()
        ->and($reader->hasRole(AdminRole::UsersReader->value))->toBeTrue()
        ->and($reader->hasRole(AdminRole::Admin->value))->toBeFalse();
});
