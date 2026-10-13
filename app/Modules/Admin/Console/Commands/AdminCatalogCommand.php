<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Commands;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class AdminCatalogCommand extends Command
{
    protected const string ACTION_GRANT = 'grant';

    protected const string ACTION_REVOKE = 'revoke';

    protected const string GUARD = 'admin';

    protected function isValidAction(mixed $action): bool
    {
        return \in_array($action, [self::ACTION_GRANT, self::ACTION_REVOKE], true);
    }

    /**
     * Locks the `admin` role and the `admin.access` permission together with the requested role.
     * Reports the failure and returns null when the authorization catalog is not initialized.
     *
     * @return array{role: Role, adminAccessPermission: Permission}|null
     */
    protected function lockCatalogRole(AdminRole $adminRole): ?array
    {
        $adminRoleLock = Role::query()
            ->where('name', AdminRole::Admin->value)
            ->where('guard_name', self::GUARD)
            ->lockForUpdate()
            ->first();

        $adminAccessPermission = Permission::query()
            ->where('name', AdminPermission::AdminAccess->value)
            ->where('guard_name', self::GUARD)
            ->lockForUpdate()
            ->first();

        if (! $adminRoleLock instanceof Role || ! $adminAccessPermission instanceof Permission) {
            $this->error('Admin authorization is not initialized. Run the Admin authorization seeder first.');

            return null;
        }

        $role = $adminRole === AdminRole::Admin
            ? $adminRoleLock
            : Role::query()
                ->where('name', $adminRole->value)
                ->where('guard_name', self::GUARD)
                ->lockForUpdate()
                ->first();

        if (! $role instanceof Role) {
            $this->error("The Admin role {$adminRole->value} is not initialized. Run the Admin authorization seeder first.");

            return null;
        }

        return ['role' => $role, 'adminAccessPermission' => $adminAccessPermission];
    }
}
