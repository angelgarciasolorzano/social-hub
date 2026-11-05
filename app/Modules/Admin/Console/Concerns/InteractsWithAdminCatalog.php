<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Concerns;

use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;

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
     * The role of the `admin` guard with the given name, system or custom. A catalog name that is not
     * stored yet resolves to an unsaved role, so the service reports the uninitialized catalog; any
     * other unknown name is reported here and resolves to null.
     */
    protected function adminGuardRole(string $roleName): ?Role
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', self::GUARD)
            ->first();

        if ($role instanceof Role) {
            return $role;
        }

        if (AdminRole::tryFrom($roleName) instanceof AdminRole) {
            return new Role(['name' => $roleName, 'guard_name' => self::GUARD]);
        }

        $this->error(\sprintf(
            'Unknown Admin role "%s". Available roles: %s.',
            $roleName,
            implode(', ', $this->availableRoleNames()),
        ));

        return null;
    }

    /**
     * The console message of a failure that `AdminAuthorizationService` reported, or null for a
     * failure the command reports in its own words.
     */
    protected function catalogFailureMessage(
        AdminAuthorizationFailure $adminAuthorizationFailure,
        Role $role,
        ?AdminPermission $adminPermission = null,
    ): ?string {
        return match ($adminAuthorizationFailure) {
            AdminAuthorizationFailure::CatalogNotInitialized => 'Admin authorization is not initialized. Run the Admin authorization seeder first.',
            AdminAuthorizationFailure::RoleNotInitialized => "The Admin role {$role->name} is not initialized. Run the Admin authorization seeder first.",
            AdminAuthorizationFailure::PermissionNotInitialized => "The Admin permission {$adminPermission?->value} is not initialized. Run the Admin authorization seeder first.",
            AdminAuthorizationFailure::RoleLocked => "The Admin role {$role->name} is locked: its permissions cannot change.",
            AdminAuthorizationFailure::PermissionDependencyMissing => $adminPermission instanceof AdminPermission
                ? $this->dependencyFailureMessage($role, $adminPermission)
                : null,
            default => null,
        };
    }

    /**
     * Explain which permissions the grant lacks, or which permissions of the role still need the
     * revoked one.
     */
    private function dependencyFailureMessage(Role $role, AdminPermission $adminPermission): string
    {
        $heldPermissions = [];

        $permissions = Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereHas('roles', fn (Builder $builder): Builder => $builder->whereKey($role->getKey()))
            ->get();

        foreach ($permissions as $permission) {
            $heldPermission = AdminPermission::tryFrom($permission->name);

            if ($heldPermission instanceof AdminPermission) {
                $heldPermissions[] = $heldPermission;
            }
        }

        if (! \in_array($adminPermission, $heldPermissions, true)) {
            return \sprintf(
                'Permission %s requires %s. Grant them to role %s first.',
                $adminPermission->value,
                $this->permissionList(AdminPermission::missingRequirements([...$heldPermissions, $adminPermission])),
                $role->name,
            );
        }

        return \sprintf(
            'Permission %s is required by %s on role %s. Revoke them first.',
            $adminPermission->value,
            $this->permissionList(array_values(array_filter(
                $adminPermission->dependents(),
                static fn (AdminPermission $adminPermission): bool => \in_array($adminPermission, $heldPermissions, true),
            ))),
            $role->name,
        );
    }

    /**
     * @param  list<AdminPermission>  $adminPermissions
     */
    private function permissionList(array $adminPermissions): string
    {
        return implode(', ', array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->value,
            $adminPermissions,
        ));
    }

    /**
     * The stored roles of the `admin` guard, or the catalog when none is stored yet.
     *
     * @return list<string>
     */
    private function availableRoleNames(): array
    {
        $roleNames = [];

        foreach (Role::query()->where('guard_name', self::GUARD)->orderBy('name')->get() as $role) {
            $roleNames[] = $role->name;
        }

        if ($roleNames !== []) {
            return $roleNames;
        }

        return array_map(static fn (AdminRole $adminRole): string => $adminRole->value, AdminRole::cases());
    }
}
