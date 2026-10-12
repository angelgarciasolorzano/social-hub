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

            /** @var array<string, Permission> $permissions */
            $permissions = [];

            foreach (AdminPermission::cases() as $adminPermission) {
                $permissions[$adminPermission->value] = Permission::query()->firstOrCreate([
                    'name' => $adminPermission->value,
                    'guard_name' => self::GUARD,
                ]);
            }

            $adminRole = Role::query()->firstOrCreate([
                'name' => AdminRole::Admin->value,
                'guard_name' => self::GUARD,
            ]);

            if ($adminRole->wasRecentlyCreated) {
                $adminRole->givePermissionTo($permissions[AdminPermission::AdminAccess->value]);
            }

            $usersReaderRole = Role::query()->firstOrCreate([
                'name' => AdminRole::UsersReader->value,
                'guard_name' => self::GUARD,
            ]);

            if ($usersReaderRole->wasRecentlyCreated) {
                $usersReaderRole->givePermissionTo([
                    $permissions[AdminPermission::UsersViewAny->value],
                    $permissions[AdminPermission::UsersView->value],
                ]);
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
}
