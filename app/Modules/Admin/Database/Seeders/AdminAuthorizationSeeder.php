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
            $this->renameLegacyPermissions();
            $this->renameLegacyRoles();

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
                AdminRole::AdminPostsReader->value => [
                    AdminPermission::AdminPostsViewAny,
                    AdminPermission::AdminPostsView,
                ],
                AdminRole::AdminDashboardReader->value => [AdminPermission::AdminDashboardView],
                AdminRole::AdminActivityReader->value => [
                    AdminPermission::AdminActivityViewAny,
                    AdminPermission::AdminActivityView,
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

    private function renameLegacyPermissions(): void
    {
        foreach ([
            'users.viewAny' => AdminPermission::AdminUsersViewAny,
            'users.view' => AdminPermission::AdminUsersView,
        ] as $legacyName => $adminPermission) {
            Permission::query()
                ->where('name', $legacyName)
                ->where('guard_name', self::GUARD)
                ->update(['name' => $adminPermission->value]);
        }
    }

    private function renameLegacyRoles(): void
    {
        Role::query()
            ->where('name', 'users-reader')
            ->where('guard_name', self::GUARD)
            ->update(['name' => AdminRole::AdminUsersReader->value]);
    }
}
