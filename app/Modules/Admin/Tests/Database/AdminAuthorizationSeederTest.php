<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use Spatie\Permission\Models\Role;

it('seeds each role with only its intended permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $adminRole = Role::query()
        ->where('name', AdminRole::Admin->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();

    $usersReaderRole = Role::query()
        ->where('name', AdminRole::UsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();

    $this->assertDatabaseCount('roles', 2);
    $this->assertDatabaseCount('permissions', 3);

    foreach (AdminRole::cases() as $adminRoleCase) {
        $this->assertDatabaseHas('roles', [
            'name' => $adminRoleCase->value,
            'guard_name' => 'admin',
        ]);
        $this->assertDatabaseMissing('roles', [
            'name' => $adminRoleCase->value,
            'guard_name' => 'web',
        ]);
    }

    foreach (AdminPermission::cases() as $adminPermissionCase) {
        $this->assertDatabaseHas('permissions', [
            'name' => $adminPermissionCase->value,
            'guard_name' => 'admin',
        ]);
        $this->assertDatabaseMissing('permissions', [
            'name' => $adminPermissionCase->value,
            'guard_name' => 'web',
        ]);
    }

    expect($adminRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([AdminPermission::AdminAccess->value])
        ->and($usersReaderRole->permissions->pluck('name')->sort()->values()
            ->all())
        ->toBe([AdminPermission::UsersView->value, AdminPermission::UsersViewAny->value]);
});

it('keeps a revoked permission revoked when the seeder runs again', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $usersReaderRole = Role::query()
        ->where('name', AdminRole::UsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $user = createUser();
    $user->assignRole($usersReaderRole);

    $usersReaderRole->revokePermissionTo(AdminPermission::UsersViewAny->value);

    $this->seed(AdminAuthorizationSeeder::class);

    $user->refresh();

    $remainingPermissions = Role::query()
        ->where('name', AdminRole::UsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail()
        ->permissions
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    $this->assertDatabaseCount('roles', 2);
    $this->assertDatabaseCount('permissions', 3);
    $this->assertDatabaseHas('model_has_roles', [
        'role_id' => $usersReaderRole->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    expect($remainingPermissions)->toBe([AdminPermission::UsersView->value])
        ->and($user->hasRole(AdminRole::UsersReader->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::UsersView->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::UsersViewAny->value, 'admin'))->toBeFalse();
});
