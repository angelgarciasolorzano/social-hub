<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Commands;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Admin\Console\Concerns\InteractsWithAdminCatalog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Signature('admin:role-permissions {action : grant or revoke} {role : Role name from the Admin catalog} {permission : Permission name from the Admin catalog} {--actor= : Email of the administrator running the command}')]
#[Description('Grant or revoke a cataloged permission for an Admin role.')]
class AdminRolePermissionsCommand extends Command
{
    use InteractsWithAdminCatalog;

    public function handle(ActivityLogger $activityLogger): int
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

        $actorEmail = $this->actorEmail($this->option('actor'));

        if ($actorEmail === null) {
            return self::INVALID;
        }

        return DB::transaction(function () use ($action, $adminRole, $adminPermission, $actorEmail, $activityLogger): int {
            $lockedCatalog = $this->lockCatalogRole($adminRole);

            if ($lockedCatalog === null) {
                return self::FAILURE;
            }

            $actor = $this->resolveActor($actorEmail);

            if (! $actor instanceof User) {
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

            $roleHadPermission = $role->permissions()
                ->whereKey($permission->getKey())
                ->exists();
            $auditProperties = ['role' => $adminRole->value, 'permission' => $adminPermission->value];

            if ($action === self::ACTION_GRANT) {
                $role->givePermissionTo($permission);

                if (! $roleHadPermission) {
                    $activityLogger->record(ActivityLogAdminEvent::RolePermissionGranted, $role, $actor, $auditProperties);
                }

                $this->info("Permission {$adminPermission->value} is granted to role {$adminRole->value}.");

                return self::SUCCESS;
            }

            if (
                $adminPermission === AdminPermission::AdminAccess
                && $roleHadPermission
                && ! $this->aUserRetainsAdminAccessAfterRevocation($role, $permission)
            ) {
                $this->error('Cannot revoke admin.access because it would leave no user with panel access.');

                return self::FAILURE;
            }

            $role->revokePermissionTo($permission);

            if ($roleHadPermission) {
                $activityLogger->record(ActivityLogAdminEvent::RolePermissionRevoked, $role, $actor, $auditProperties);
            }

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
