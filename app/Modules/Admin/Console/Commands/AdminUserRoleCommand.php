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

#[Signature('admin:user-role {action : grant or revoke} {email : User account email} {role : Role name from the Admin catalog} {--actor= : Email of the administrator running the command}')]
#[Description('Grant or revoke a cataloged Admin role for a user.')]
class AdminUserRoleCommand extends Command
{
    use InteractsWithAdminCatalog;

    public function handle(ActivityLogger $activityLogger): int
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

        return DB::transaction(function () use ($action, $email, $adminRole, $actorEmail, $activityLogger): int {
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

            $user = User::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (! $user instanceof User) {
                $this->error("No user was found with the email {$email}.");

                return self::FAILURE;
            }

            $hadRole = $user->hasRole($role);

            if ($action === self::ACTION_GRANT) {
                $user->assignRole($role);

                if (! $hadRole) {
                    $activityLogger->record(ActivityLogAdminEvent::RoleGranted, $user, $actor, ['role' => $adminRole->value]);
                }

                $this->info("Role {$adminRole->value} is assigned to {$email}.");

                return self::SUCCESS;
            }

            $higherOrderExpectation = $role->permissions()
                ->whereKey($adminAccessPermission->getKey())
                ->exists();

            if (
                $hadRole
                && $higherOrderExpectation
                && ! $this->userRetainsAdminAccessAfterRevocation($user, $role, $adminAccessPermission)
                && ! User::query()
                    ->permission(AdminPermission::AdminAccess->value)
                    ->whereKeyNot($user->getKey())
                    ->exists()
            ) {
                $this->error('Cannot revoke this role because it would leave no user with admin.access.');

                return self::FAILURE;
            }

            $user->removeRole($role);

            if ($hadRole) {
                $activityLogger->record(ActivityLogAdminEvent::RoleRevoked, $user, $actor, ['role' => $adminRole->value]);
            }

            $this->info("Role {$adminRole->value} is absent from {$email}.");

            return self::SUCCESS;
        });
    }

    private function userRetainsAdminAccessAfterRevocation(
        User $user,
        Role $revokedRole,
        Permission $adminAccessPermission,
    ): bool {
        if ($user->hasDirectPermission($adminAccessPermission)) {
            return true;
        }

        return $user->roles()
            ->whereKeyNot($revokedRole->getKey())
            ->whereHas(
                'permissions',
                fn (Builder $builder): Builder => $builder
                    ->whereKey($adminAccessPermission->getKey()),
            )
            ->exists();
    }
}
