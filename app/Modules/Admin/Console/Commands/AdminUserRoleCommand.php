<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Commands;

use App\Modules\Admin\Console\Concerns\InteractsWithAdminCatalog;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\User\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('admin:user-role {action : grant or revoke} {email : User account email} {role : Role name from the Admin catalog} {--actor= : Email of the administrator running the command}')]
#[Description('Grant or revoke a cataloged Admin role for a user.')]
class AdminUserRoleCommand extends Command
{
    use InteractsWithAdminCatalog;

    public function handle(AdminAuthorizationService $adminAuthorizationService): int
    {
        $action = $this->argument('action');
        $email = $this->argument('email');
        $roleName = $this->argument('role');

        if (! $this->isValidAction($action)) {
            $this->error('Action must be "grant" or "revoke".');

            return self::INVALID;
        }

        $adminRole = AdminRole::tryFrom($roleName);

        if (! $adminRole instanceof AdminRole) {
            $availableRoles = array_map(
                fn (AdminRole $adminRole): string => $adminRole->value,
                AdminRole::cases(),
            );

            $this->error(\sprintf(
                'Unknown Admin role "%s". Available roles: %s.',
                $roleName,
                implode(', ', $availableRoles),
            ));

            return self::INVALID;
        }

        if ($email === '') {
            $this->error('A user email address is required.');

            return self::INVALID;
        }

        $actorEmail = $this->actorEmail($this->option('actor'));

        if ($actorEmail === null) {
            return self::INVALID;
        }

        return DB::transaction(function () use ($action, $email, $adminRole, $actorEmail, $adminAuthorizationService): int {
            try {
                $adminAuthorizationService->lockCatalog($adminRole);
            } catch (AdminAuthorizationException $adminAuthorizationException) {
                return $this->reportFailure($adminAuthorizationException->failure, $adminRole);
            }

            $actor = $this->resolveActor($actorEmail);

            if (! $actor instanceof User) {
                return self::FAILURE;
            }

            $user = User::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (! $user instanceof User) {
                $this->error("No user was found with the email {$email}.");

                return self::FAILURE;
            }

            try {
                if ($action === self::ACTION_GRANT) {
                    $adminAuthorizationService->grantRole($user, $adminRole, $actor);

                    $this->info("Role {$adminRole->value} is assigned to {$email}.");

                    return self::SUCCESS;
                }

                $adminAuthorizationService->revokeRole($user, $adminRole, $actor);
            } catch (AdminAuthorizationException $adminAuthorizationException) {
                return $this->reportFailure($adminAuthorizationException->failure, $adminRole);
            }

            $this->info("Role {$adminRole->value} is absent from {$email}.");

            return self::SUCCESS;
        });
    }

    private function reportFailure(AdminAuthorizationFailure $adminAuthorizationFailure, AdminRole $adminRole): int
    {
        $this->error(
            $this->catalogFailureMessage($adminAuthorizationFailure, $adminRole)
                ?? 'Cannot revoke this role because it would leave no user with admin.access.',
        );

        return self::FAILURE;
    }
}
