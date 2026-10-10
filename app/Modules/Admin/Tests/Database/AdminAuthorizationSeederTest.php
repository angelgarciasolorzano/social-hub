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
        ->where('guard_name', 'web')
        ->firstOrFail();

    $usersReaderRole = Role::query()
        ->where('name', AdminRole::UsersReader->value)
        ->where('guard_name', 'web')
        ->firstOrFail();

    $this->assertDatabaseCount('roles', 2);
    $this->assertDatabaseCount('permissions', 3);

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
        ->where('guard_name', 'web')
        ->firstOrFail();

    $usersReaderRole->revokePermissionTo(AdminPermission::UsersViewAny->value);

    $this->seed(AdminAuthorizationSeeder::class);

    $remainingPermissions = Role::query()
        ->where('name', AdminRole::UsersReader->value)
        ->where('guard_name', 'web')
        ->firstOrFail()
        ->permissions
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    $this->assertDatabaseCount('roles', 2);
    $this->assertDatabaseCount('permissions', 3);

    expect($remainingPermissions)->toBe([AdminPermission::UsersView->value]);
});
