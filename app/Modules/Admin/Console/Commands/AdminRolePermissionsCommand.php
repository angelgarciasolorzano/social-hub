<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Commands;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Signature('admin:role-permissions {action : grant or revoke} {role : Role name from the Admin catalog} {permission : Permission name from the Admin catalog}')]
#[Description('Grant or revoke a cataloged permission for an Admin role.')]
class AdminRolePermissionsCommand extends AdminCatalogCommand
{
    public function handle(): int
    {
        $action = $this->argument('action');
        $roleName = $this->argument('role');
        $permissionName = $this->argument('permission');

        if (! $this->isValidAction($action)) {
            $this->error('Action must be "grant" or "revoke".');

            return self::INVALID;
        }

        $adminRole = AdminRole::tryFrom($roleName);

        if (! $adminRole instanceof AdminRole) {
            $this->error("Unknown Admin role \"{$roleName}\".");

            return self::INVALID;
        }

        $adminPermission = AdminPermission::tryFrom($permissionName);

        if (! $adminPermission instanceof AdminPermission) {
            $this->error("Unknown Admin permission \"{$permissionName}\".");

            return self::INVALID;
        }

        return DB::transaction(function () use ($action, $adminRole, $adminPermission): int {
            $lockedCatalog = $this->lockCatalogRole($adminRole);

            if ($lockedCatalog === null) {
                return self::FAILURE;
            }

            $role = $lockedCatalog['role'];
            $adminAccessPermission = $lockedCatalog['adminAccessPermission'];

            $permission = $adminPermission === AdminPermission::AdminAccess
                ? $adminAccessPermission
                : Permission::query()
                    ->where('name', $adminPermission->value)
                    ->where('guard_name', self::GUARD)
                    ->first();

            if (! $permission instanceof Permission) {
                $this->error("The Admin permission {$adminPermission->value} is not initialized. Run the Admin authorization seeder first.");

                return self::FAILURE;
            }

            if ($action === self::ACTION_GRANT) {
                $role->givePermissionTo($permission);

                $this->info("Permission {$adminPermission->value} is granted to role {$adminRole->value}.");

                return self::SUCCESS;
            }

            if (
                $adminPermission === AdminPermission::AdminAccess
                && $role->permissions()
                    ->whereKey($permission->getKey())
                    ->exists()
                && ! $this->aUserRetainsAdminAccessAfterRevocation($role, $permission)
            ) {
                $this->error('Cannot revoke admin.access because it would leave no user with panel access.');

                return self::FAILURE;
            }

            $role->revokePermissionTo($permission);

            $this->info("Permission {$adminPermission->value} is revoked from role {$adminRole->value}.");

            return self::SUCCESS;
        });
    }

    private function aUserRetainsAdminAccessAfterRevocation(Role $revokedRole, Permission $adminAccessPermission): bool
    {
        if (User::query()
            ->whereHas(
                'permissions',
                fn (Builder $builder): Builder => $builder
                    ->whereKey($adminAccessPermission->getKey()),
            )
            ->exists()) {
            return true;
        }

        return User::query()
            ->whereHas(
                'roles',
                fn (Builder $builder): Builder => $builder
                    ->whereKeyNot($revokedRole->getKey())
                    ->whereHas(
                        'permissions',
                        fn (Builder $builder): Builder => $builder
                            ->whereKey($adminAccessPermission->getKey()),
                    ),
            )
            ->exists();
    }
}
