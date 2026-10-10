<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Commands;

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

#[Signature('admin:user-role {action : grant or revoke} {email : User account email} {role : Role name from the Admin catalog}')]
#[Description('Grant or revoke a cataloged Admin role for a user.')]
class AdminUserRoleCommand extends Command
{
    private const string ACTION_GRANT = 'grant';

    private const string ACTION_REVOKE = 'revoke';

    private const string GUARD = 'web';

    public function handle(): int
    {
        $action = $this->argument('action');
        $email = $this->argument('email');
        $roleName = $this->argument('role');

        if (! in_array($action, [self::ACTION_GRANT, self::ACTION_REVOKE], true)) {
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

        return DB::transaction(function () use ($action, $email, $adminRole): int {
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

                return self::FAILURE;
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

            if ($action === self::ACTION_GRANT) {
                $user->assignRole($role);

                $this->info("Role {$adminRole->value} is assigned to {$email}.");

                return self::SUCCESS;
            }

            $higherOrderExpectation = $role->permissions()
                ->whereKey($adminAccessPermission->getKey())
                ->exists();

            if (
                $user->hasRole($role)
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
