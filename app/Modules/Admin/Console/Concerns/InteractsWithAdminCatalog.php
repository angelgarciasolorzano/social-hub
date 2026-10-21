<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Concerns;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @phpstan-require-extends Command
 */
trait InteractsWithAdminCatalog
{
    protected const string ACTION_GRANT = 'grant';

    protected const string ACTION_REVOKE = 'revoke';

    protected const string GUARD = 'admin';

    protected function isValidAction(mixed $action): bool
    {
        return \in_array($action, [self::ACTION_GRANT, self::ACTION_REVOKE], true);
    }

    /**
     * The `--actor` email recorded as the person who ran the command.
     * Reports the error and returns null when the option is missing.
     */
    protected function actorEmail(mixed $actorEmail): ?string
    {
        if (! \is_string($actorEmail) || trim($actorEmail) === '') {
            $this->error('The --actor option is required: pass the email of the administrator running this command.');

            return null;
        }

        return trim($actorEmail);
    }

    /**
     * Find the verified account behind `--actor`, which must hold admin.access unless
     * the command bootstraps the first administrator. Reports why it is rejected.
     */
    protected function resolveActor(string $actorEmail, bool $requiresAdminAccess = true): ?User
    {
        $actor = User::query()->where('email', $actorEmail)->first();

        if (! $actor instanceof User) {
            $this->error("No actor was found with the email {$actorEmail}.");

            return null;
        }

        if (! $actor->hasVerifiedEmail()) {
            $this->error("The actor {$actorEmail} must have a verified email.");

            return null;
        }

        if ($requiresAdminAccess && ! $actor->can(AdminPermission::AdminAccess->value)) {
            $this->error("The actor {$actorEmail} must have admin.access.");

            return null;
        }

        return $actor;
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
