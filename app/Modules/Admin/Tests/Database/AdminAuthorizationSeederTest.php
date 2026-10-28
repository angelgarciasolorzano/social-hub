<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use Spatie\Permission\Models\Role;

/**
 * @return array<int, mixed>
 */
function usersReaderPermissionNames(): array
{
    return Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail()
        ->permissions
        ->pluck('name')
        ->sort()
        ->values()
        ->all();
}

it('seeds each role with only its intended permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $adminRole = Role::query()
        ->where('name', AdminRole::Admin->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();

    $usersReaderRole = Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $usersProvisionerRole = Role::query()
        ->where('name', AdminRole::AdminUsersProvisioner->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $usersModeratorRole = Role::query()
        ->where('name', AdminRole::AdminUsersModerator->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $postsReaderRole = Role::query()
        ->where('name', AdminRole::AdminPostsReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $dashboardReaderRole = Role::query()
        ->where('name', AdminRole::AdminDashboardReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $activityReaderRole = Role::query()
        ->where('name', AdminRole::AdminActivityReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $backupsReaderRole = Role::query()
        ->where('name', AdminRole::AdminBackupsReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $backupsOperatorRole = Role::query()
        ->where('name', AdminRole::AdminBackupsOperator->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();

    $this->assertDatabaseCount('roles', count(AdminRole::cases()));
    $this->assertDatabaseCount('permissions', count(AdminPermission::cases()));

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
        ->toBe([AdminPermission::AdminUsersView->value, AdminPermission::AdminUsersViewAny->value])
        ->and($usersProvisionerRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([AdminPermission::AdminUsersCreate->value, AdminPermission::AdminUsersViewAny->value])
        ->and($usersModeratorRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([
            AdminPermission::AdminUsersReactivate->value,
            AdminPermission::AdminUsersSuspend->value,
            AdminPermission::AdminUsersView->value,
            AdminPermission::AdminUsersViewAny->value,
        ])
        ->and($postsReaderRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([AdminPermission::AdminPostsView->value, AdminPermission::AdminPostsViewAny->value])
        ->and($dashboardReaderRole->permissions->pluck('name')->all())
        ->toBe([AdminPermission::AdminDashboardView->value])
        ->and($activityReaderRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([AdminPermission::AdminActivityView->value, AdminPermission::AdminActivityViewAny->value])
        ->and($backupsReaderRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([AdminPermission::AdminBackupsView->value])
        ->and($backupsOperatorRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([
            AdminPermission::AdminBackupsCreate->value,
            AdminPermission::AdminBackupsDownload->value,
            AdminPermission::AdminBackupsView->value,
        ]);
});

it('gives the announcements manager only the announcement permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $announcementsManagerRole = Role::query()
        ->where('name', AdminRole::AdminAnnouncementsManager->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $user = createUser();
    $user->assignRole($announcementsManagerRole);

    expect($announcementsManagerRole->permissions->pluck('name')->sort()->values()->all())
        ->toBe([
            AdminPermission::AdminAnnouncementsCreate->value,
            AdminPermission::AdminAnnouncementsDelete->value,
            AdminPermission::AdminAnnouncementsUpdate->value,
            AdminPermission::AdminAnnouncementsView->value,
            AdminPermission::AdminAnnouncementsViewAny->value,
        ])
        ->and($user->hasPermissionTo(AdminPermission::AdminAccess->value, 'admin'))->toBeFalse()
        ->and($user->hasPermissionTo(AdminPermission::AdminUsersViewAny->value, 'admin'))->toBeFalse();
});

it('gives the activity reader only the activity log permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminActivityReader->value);

    expect($user->hasPermissionTo(AdminPermission::AdminActivityViewAny->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::AdminActivityView->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::AdminAccess->value, 'admin'))->toBeFalse()
        ->and($user->hasPermissionTo(AdminPermission::AdminUsersViewAny->value, 'admin'))->toBeFalse()
        ->and($user->hasPermissionTo(AdminPermission::AdminPostsViewAny->value, 'admin'))->toBeFalse();
});

it('does not grant activity log access through admin.access alone', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    expect($user->hasPermissionTo(AdminPermission::AdminActivityViewAny->value, 'admin'))->toBeFalse()
        ->and($user->hasPermissionTo(AdminPermission::AdminActivityView->value, 'admin'))->toBeFalse();
});

it('allows the provisioner to list users and create invitations without other section access', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersProvisioner->value);

    expect($user->hasPermissionTo(AdminPermission::AdminUsersViewAny->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::AdminUsersCreate->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::AdminUsersView->value, 'admin'))->toBeFalse()
        ->and($user->hasPermissionTo(AdminPermission::AdminPostsViewAny->value, 'admin'))->toBeFalse()
        ->and($user->hasPermissionTo(AdminPermission::AdminPostsView->value, 'admin'))->toBeFalse()
        ->and($user->hasPermissionTo(AdminPermission::AdminDashboardView->value, 'admin'))->toBeFalse();
});

it('preserves role assignments and revoked permissions when the seeder runs again', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $usersReaderRole = Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail();
    $user = createUser();
    $user->assignRole($usersReaderRole);

    $usersReaderRole->revokePermissionTo(AdminPermission::AdminUsersViewAny->value);

    $this->seed(AdminAuthorizationSeeder::class);

    $user->refresh();

    $remainingPermissions = usersReaderPermissionNames();

    $this->assertDatabaseCount('roles', count(AdminRole::cases()));
    $this->assertDatabaseCount('permissions', count(AdminPermission::cases()));
    $this->assertDatabaseHas('model_has_roles', [
        'role_id' => $usersReaderRole->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    expect($remainingPermissions)->toBe([AdminPermission::AdminUsersView->value])
        ->and($user->hasRole(AdminRole::AdminUsersReader->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::AdminUsersView->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::AdminUsersViewAny->value, 'admin'))->toBeFalse();
});
