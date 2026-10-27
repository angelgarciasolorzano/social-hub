<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Seeders;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminAuthorizationSeeder extends Seeder
{
    private const string GUARD = 'admin';

    public function run(PermissionRegistrar $permissionRegistrar): void
    {
        $permissionRegistrar->forgetCachedPermissions();

        DB::transaction(function (): void {
            /** @var array<string, Permission> $permissions */
            $permissions = [];

            foreach (AdminPermission::cases() as $adminPermission) {
                $permissions[$adminPermission->value] = Permission::query()->firstOrCreate([
                    'name' => $adminPermission->value,
                    'guard_name' => self::GUARD,
                ]);
            }

            /** @var array<string, list<AdminPermission>> $permissionsByRole */
            $permissionsByRole = [
                AdminRole::Admin->value => [AdminPermission::AdminAccess],
                AdminRole::AdminUsersReader->value => [
                    AdminPermission::AdminUsersViewAny,
                    AdminPermission::AdminUsersView,
                ],
                AdminRole::AdminUsersProvisioner->value => [
                    AdminPermission::AdminUsersViewAny,
                    AdminPermission::AdminUsersCreate,
                ],
                AdminRole::AdminUsersModerator->value => [
                    AdminPermission::AdminUsersViewAny,
                    AdminPermission::AdminUsersView,
                    AdminPermission::AdminUsersSuspend,
                    AdminPermission::AdminUsersReactivate,
                ],
                AdminRole::AdminPostsReader->value => [
                    AdminPermission::AdminPostsViewAny,
                    AdminPermission::AdminPostsView,
                ],
                AdminRole::AdminDashboardReader->value => [AdminPermission::AdminDashboardView],
                AdminRole::AdminActivityReader->value => [
                    AdminPermission::AdminActivityViewAny,
                    AdminPermission::AdminActivityView,
                ],
                AdminRole::AdminBackupsReader->value => [AdminPermission::AdminBackupsView],
                AdminRole::AdminBackupsOperator->value => [
                    AdminPermission::AdminBackupsView,
                    AdminPermission::AdminBackupsCreate,
                    AdminPermission::AdminBackupsDownload,
                ],
                AdminRole::AdminAnnouncementsManager->value => [
                    AdminPermission::AdminAnnouncementsViewAny,
                    AdminPermission::AdminAnnouncementsView,
                    AdminPermission::AdminAnnouncementsCreate,
                    AdminPermission::AdminAnnouncementsUpdate,
                    AdminPermission::AdminAnnouncementsDelete,
                ],
            ];

            foreach ($permissionsByRole as $roleName => $adminPermissions) {
                $role = Role::query()->firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => self::GUARD,
                ]);

                if (! $role->wasRecentlyCreated) {
                    continue;
                }

                $rolePermissionModels = [];

                foreach ($adminPermissions as $adminPermission) {
                    $rolePermissionModels[] = $permissions[$adminPermission->value];
                }

                $role->givePermissionTo($rolePermissionModels);
            }
        });

        $permissionRegistrar->forgetCachedPermissions();
    }
}
