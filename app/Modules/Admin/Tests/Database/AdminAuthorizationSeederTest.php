<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use Spatie\Permission\Models\Permission;
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
    $postsReaderRole = Role::query()
        ->where('name', AdminRole::PostsReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $dashboardReaderRole = Role::query()
        ->where('name', AdminRole::DashboardReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();

    $this->assertDatabaseCount('roles', 4);
    $this->assertDatabaseCount('permissions', 6);

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
        ->toBe([AdminPermission::UsersView->value, AdminPermission::UsersViewAny->value])
        ->and($postsReaderRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([AdminPermission::PostsView->value, AdminPermission::PostsViewAny->value])
        ->and($dashboardReaderRole->permissions->pluck('name')->all())
        ->toBe([AdminPermission::DashboardView->value]);
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

    $this->assertDatabaseCount('roles', 4);
    $this->assertDatabaseCount('permissions', 6);
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

it('renames the legacy users reader role without losing users or role permissions', function (): void {
    $usersViewAnyPermission = Permission::query()->create([
        'name' => AdminPermission::UsersViewAny->value,
        'guard_name' => 'admin',
    ]);
    $usersViewPermission = Permission::query()->create([
        'name' => AdminPermission::UsersView->value,
        'guard_name' => 'admin',
    ]);
    $legacyUsersReaderRole = Role::query()->create([
        'name' => 'users-reader',
        'guard_name' => 'admin',
    ]);
    $legacyUsersReaderRole->givePermissionTo([
        $usersViewAnyPermission,
        $usersViewPermission,
    ]);
    $user = createUser();
    $user->assignRole($legacyUsersReaderRole);

    $legacyUsersReaderRole->revokePermissionTo($usersViewAnyPermission);

    $this->seed(AdminAuthorizationSeeder::class);
    $this->seed(AdminAuthorizationSeeder::class);

    $renamedUsersReaderRole = Role::query()
        ->where('name', AdminRole::UsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $remainingPermissions = $renamedUsersReaderRole->permissions
        ->pluck('name')
        ->sort()
        ->values()
        ->all();
    $user->refresh();

    $this->assertDatabaseCount('roles', 4);
    $this->assertDatabaseHas('roles', [
        'id' => $legacyUsersReaderRole->getKey(),
        'name' => AdminRole::UsersReader->value,
        'guard_name' => 'admin',
    ]);
    $this->assertDatabaseMissing('roles', [
        'name' => 'users-reader',
        'guard_name' => 'admin',
    ]);
    $this->assertDatabaseHas('model_has_roles', [
        'role_id' => $legacyUsersReaderRole->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);
    $this->assertDatabaseHas('role_has_permissions', [
        'role_id' => $legacyUsersReaderRole->getKey(),
        'permission_id' => $usersViewPermission->getKey(),
    ]);
    $this->assertDatabaseMissing('role_has_permissions', [
        'role_id' => $legacyUsersReaderRole->getKey(),
        'permission_id' => $usersViewAnyPermission->getKey(),
    ]);

    expect($remainingPermissions)->toBe([AdminPermission::UsersView->value])
        ->and($user->hasRole(AdminRole::UsersReader->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::UsersView->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::UsersViewAny->value, 'admin'))->toBeFalse();
});

it('renames legacy permissions in place while preserving role grants and revocations', function (): void {
    $legacyUsersViewAnyPermission = Permission::query()->create([
        'name' => 'users.viewAny',
        'guard_name' => 'admin',
    ]);
    $legacyUsersViewPermission = Permission::query()->create([
        'name' => 'users.view',
        'guard_name' => 'admin',
    ]);
    $usersReaderRole = Role::query()->create([
        'name' => AdminRole::UsersReader->value,
        'guard_name' => 'admin',
    ]);
    $usersReaderRole->givePermissionTo([
        $legacyUsersViewAnyPermission,
        $legacyUsersViewPermission,
    ]);
    $usersReaderRole->revokePermissionTo($legacyUsersViewAnyPermission);

    $this->seed(AdminAuthorizationSeeder::class);
    $this->seed(AdminAuthorizationSeeder::class);

    $renamedUsersReaderRole = Role::query()
        ->where('name', AdminRole::UsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $remainingPermissions = $renamedUsersReaderRole->permissions
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    $this->assertDatabaseCount('permissions', 6);
    $this->assertDatabaseHas('permissions', [
        'id' => $legacyUsersViewAnyPermission->getKey(),
        'name' => AdminPermission::UsersViewAny->value,
        'guard_name' => 'admin',
    ]);
    $this->assertDatabaseHas('permissions', [
        'id' => $legacyUsersViewPermission->getKey(),
        'name' => AdminPermission::UsersView->value,
        'guard_name' => 'admin',
    ]);
    $this->assertDatabaseMissing('permissions', [
        'name' => 'users.viewAny',
        'guard_name' => 'admin',
    ]);
    $this->assertDatabaseMissing('permissions', [
        'name' => 'users.view',
        'guard_name' => 'admin',
    ]);
    $this->assertDatabaseMissing('role_has_permissions', [
        'role_id' => $renamedUsersReaderRole->getKey(),
        'permission_id' => $legacyUsersViewAnyPermission->getKey(),
    ]);

    expect($remainingPermissions)->toBe([AdminPermission::UsersView->value]);
});
