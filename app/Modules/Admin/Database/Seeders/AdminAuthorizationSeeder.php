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
    private const string GUARD = 'web';

    public function run(PermissionRegistrar $permissionRegistrar): void
    {
        $permissionRegistrar->forgetCachedPermissions();

        DB::transaction(function (): void {
            $adminAccessPermission = Permission::query()->firstOrCreate([
                'name' => AdminPermission::AdminAccess->value,
                'guard_name' => self::GUARD,
            ]);

            $usersViewAnyPermission = Permission::query()->firstOrCreate([
                'name' => AdminPermission::UsersViewAny->value,
                'guard_name' => self::GUARD,
            ]);

            $usersViewPermission = Permission::query()->firstOrCreate([
                'name' => AdminPermission::UsersView->value,
                'guard_name' => self::GUARD,
            ]);

            $adminRole = Role::query()->firstOrCreate([
                'name' => AdminRole::Admin->value,
                'guard_name' => self::GUARD,
            ]);

            if ($adminRole->wasRecentlyCreated) {
                $adminRole->givePermissionTo($adminAccessPermission);
            }

            $usersReaderRole = Role::query()->firstOrCreate([
                'name' => AdminRole::UsersReader->value,
                'guard_name' => self::GUARD,
            ]);

            if ($usersReaderRole->wasRecentlyCreated) {
                $usersReaderRole->givePermissionTo([
                    $usersViewAnyPermission,
                    $usersViewPermission,
                ]);
            }
        });

        $permissionRegistrar->forgetCachedPermissions();
    }
}
