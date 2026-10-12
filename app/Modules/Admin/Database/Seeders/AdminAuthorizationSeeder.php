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
                AdminRole::UsersReader->value => [
                    AdminPermission::UsersViewAny,
                    AdminPermission::UsersView,
                ],
                AdminRole::PostsReader->value => [
                    AdminPermission::PostsViewAny,
                    AdminPermission::PostsView,
                ],
                AdminRole::DashboardReader->value => [AdminPermission::DashboardView],
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
            'users.viewAny' => AdminPermission::UsersViewAny,
            'users.view' => AdminPermission::UsersView,
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
            ->update(['name' => AdminRole::UsersReader->value]);
    }
}
