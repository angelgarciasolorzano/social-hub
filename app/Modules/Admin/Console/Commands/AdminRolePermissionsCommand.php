<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Commands;

use App\Modules\Admin\Console\Concerns\InteractsWithAdminCatalog;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\User\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('admin:role-permissions {action : grant or revoke} {role : Role name from the Admin catalog} {permission : Permission name from the Admin catalog} {--actor= : Email of the administrator running the command}')]
#[Description('Grant or revoke a cataloged permission for an Admin role.')]
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

        return DB::transaction(function () use ($action, $adminRole, $adminPermission, $actorEmail, $adminAuthorizationService): int {
            try {
                $adminAuthorizationService->lockCatalog($adminRole);
            } catch (AdminAuthorizationException $adminAuthorizationException) {
                return $this->reportFailure($adminAuthorizationException->failure, $adminRole, $adminPermission);
            }

            $actor = $this->resolveActor($actorEmail);

            if (! $actor instanceof User) {
                return self::FAILURE;
            }

            try {
                if ($action === self::ACTION_GRANT) {
                    $adminAuthorizationService->grantPermission($adminRole, $adminPermission, $actor);

                    $this->info("Permission {$adminPermission->value} is granted to role {$adminRole->value}.");

                    return self::SUCCESS;
                }

                $adminAuthorizationService->revokePermission($adminRole, $adminPermission, $actor);
            } catch (AdminAuthorizationException $adminAuthorizationException) {
                return $this->reportFailure($adminAuthorizationException->failure, $adminRole, $adminPermission);
            }

            $this->info("Permission {$adminPermission->value} is revoked from role {$adminRole->value}.");

            return self::SUCCESS;
        });
    }

    private function reportFailure(
        AdminAuthorizationFailure $adminAuthorizationFailure,
        AdminRole $adminRole,
        AdminPermission $adminPermission,
    ): int {
        $this->error(
            $this->catalogFailureMessage($adminAuthorizationFailure, $adminRole, $adminPermission)
                ?? 'Cannot revoke admin.access because it would leave no user with panel access.',
        );

        return self::FAILURE;
    }
}
