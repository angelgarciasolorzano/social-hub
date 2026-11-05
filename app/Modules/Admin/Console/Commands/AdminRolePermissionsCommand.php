<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Commands;

use App\Modules\Admin\Console\Concerns\InteractsWithAdminCatalog;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\User\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('admin:role-permissions {action : grant or revoke} {role : Name of an Admin role, system or custom} {permission : Permission name from the Admin catalog} {--actor= : Email of the administrator running the command}')]
#[Description('Grant or revoke a cataloged permission for an Admin role, system or custom.')]
class AdminRolePermissionsCommand extends Command
{
    use InteractsWithAdminCatalog;

    public function handle(AdminAuthorizationService $adminAuthorizationService): int
    {
        $action = $this->argument('action');
        $roleName = $this->argument('role');
        $permissionName = $this->argument('permission');

        if (! $this->isValidAction($action)) {
            $this->error('Action must be "grant" or "revoke".');

            return self::INVALID;
        }

        $role = $this->adminGuardRole($roleName);

        if (! $role instanceof Role) {
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

        return DB::transaction(function () use ($action, $role, $adminPermission, $actorEmail, $adminAuthorizationService): int {
            try {
                $adminAuthorizationService->lockCatalog($role);
            } catch (AdminAuthorizationException $adminAuthorizationException) {
                return $this->reportFailure($adminAuthorizationException->failure, $role, $adminPermission);
            }

            $actor = $this->resolveActor($actorEmail);

            if (! $actor instanceof User) {
                return self::FAILURE;
            }

            try {
                if ($action === self::ACTION_GRANT) {
                    $adminAuthorizationService->grantPermission($role, $adminPermission, $actor);

                    $this->info("Permission {$adminPermission->value} is granted to role {$role->name}.");

                    return self::SUCCESS;
                }

                $adminAuthorizationService->revokePermission($role, $adminPermission, $actor);
            } catch (AdminAuthorizationException $adminAuthorizationException) {
                return $this->reportFailure($adminAuthorizationException->failure, $role, $adminPermission);
            }

            $this->info("Permission {$adminPermission->value} is revoked from role {$role->name}.");

            return self::SUCCESS;
        });
    }

    private function reportFailure(
        AdminAuthorizationFailure $adminAuthorizationFailure,
        Role $role,
        AdminPermission $adminPermission,
    ): int {
        $this->error(
            $this->catalogFailureMessage($adminAuthorizationFailure, $role, $adminPermission)
                ?? 'Cannot revoke admin.access because it would leave no user with panel access.',
        );

        return self::FAILURE;
    }
}
