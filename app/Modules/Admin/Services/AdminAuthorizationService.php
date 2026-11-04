<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Admin\Data\AdminAuthorizationChanges;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Models\Role;
use App\User\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The only writer of the Admin authorization catalog outside the seeders: grants and revokes cataloged
 * roles and role permissions for the console commands and the panel. Each change locks the `admin`
 * role, the `admin.access` permission and the affected rows, never leaves the panel without a user
 * holding `admin.access`, and records the activity only when something changed.
 *
 * It does not authorize: the console commands validate their actor and the panel asks the policies.
 */
final readonly class AdminAuthorizationService
{
    private const string GUARD = 'admin';

    public function __construct(
        private ActivityLogger $activityLogger,
        private PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * Lock the catalog rows a change of the given role depends on, inside the caller's transaction.
     * The console commands call it first so an uninitialized catalog is reported before anything else.
     *
     * @throws AdminAuthorizationException
     */
    public function lockCatalog(AdminRole $adminRole): void
    {
        $this->lockCatalogRole($adminRole);
    }

    /**
     * Assign a cataloged role to the user; returns false when the user already had it.
     *
     * @throws AdminAuthorizationException
     */
    public function grantRole(User $user, AdminRole $adminRole, User $actor): bool
    {
        return DB::transaction(function () use ($user, $adminRole, $actor): bool {
            ['role' => $role] = $this->lockCatalogRole($adminRole);

            $this->lockUser($user);

            if ($user->hasRole($role)) {
                return false;
            }

            $user->assignRole($role);

            $this->activityLogger->record(ActivityLogAdminEvent::RoleGranted, $user, $actor, ['role' => $adminRole->value]);
            $this->forgetCachedPermissionsAfterCommit();

            return true;
        });
    }

    /**
     * Remove a cataloged role from the user; returns false when the user did not have it.
     *
     * @throws AdminAuthorizationException when it would leave no user with `admin.access`
     */
    public function revokeRole(User $user, AdminRole $adminRole, User $actor): bool
    {
        return DB::transaction(function () use ($user, $adminRole, $actor): bool {
            ['role' => $role, 'adminAccessPermission' => $adminAccessPermission] = $this->lockCatalogRole($adminRole);

            $this->lockUser($user);

            if (! $user->hasRole($role)) {
                return false;
            }

            $roleGrantsAdminAccess = $role->permissions()
                ->whereKey($adminAccessPermission->getKey())
                ->exists();

            throw_if(
                $roleGrantsAdminAccess && $this->isLastAdminAccessHolderWithout($user, $role, $adminAccessPermission),
                AdminAuthorizationException::class,
                AdminAuthorizationFailure::LastAdminAccess,
            );

            $user->removeRole($role);

            $this->activityLogger->record(ActivityLogAdminEvent::RoleRevoked, $user, $actor, ['role' => $adminRole->value]);
            $this->forgetCachedPermissionsAfterCommit();

            return true;
        });
    }

    /**
     * Grant a cataloged permission to a cataloged role; returns false when the role already had it.
     *
     * @throws AdminAuthorizationException
     */
    public function grantPermission(AdminRole $adminRole, AdminPermission $adminPermission, User $user): bool
    {
        return DB::transaction(function () use ($adminRole, $adminPermission, $user): bool {
            ['role' => $role, 'adminAccessPermission' => $adminAccessPermission] = $this->lockCatalogRole($adminRole);

            $permission = $this->catalogPermission($adminPermission, $adminAccessPermission);

            if ($role->permissions()->whereKey($permission->getKey())->exists()) {
                return false;
            }

            $role->givePermissionTo($permission);

            $this->activityLogger->record(ActivityLogAdminEvent::RolePermissionGranted, $role, $user, [
                'role' => $adminRole->value,
                'permission' => $adminPermission->value,
            ]);

            $this->forgetCachedPermissionsAfterCommit();

            return true;
        });
    }

    /**
     * Revoke a cataloged permission from a cataloged role; returns false when the role did not have it.
     *
     * @throws AdminAuthorizationException when revoking `admin.access` would leave no user with it
     */
    public function revokePermission(AdminRole $adminRole, AdminPermission $adminPermission, User $user): bool
    {
        return DB::transaction(function () use ($adminRole, $adminPermission, $user): bool {
            ['role' => $role, 'adminAccessPermission' => $adminAccessPermission] = $this->lockCatalogRole($adminRole);

            $permission = $this->catalogPermission($adminPermission, $adminAccessPermission);

            if (! $role->permissions()->whereKey($permission->getKey())->exists()) {
                return false;
            }

            throw_if(
                $adminPermission === AdminPermission::AdminAccess
                    && ! $this->aUserRetainsAdminAccessAfterRevocation($role, $adminAccessPermission),
                AdminAuthorizationException::class,
                AdminAuthorizationFailure::LastAdminAccess,
            );

            $role->revokePermissionTo($permission);

            $this->activityLogger->record(ActivityLogAdminEvent::RolePermissionRevoked, $role, $user, [
                'role' => $adminRole->value,
                'permission' => $adminPermission->value,
            ]);

            $this->forgetCachedPermissionsAfterCommit();

            return true;
        });
    }

    /**
     * Leave the user with exactly the selected cataloged roles, in one transaction. Roles outside the
     * catalog are kept. Grants run before revocations, so moving access between roles never trips the
     * last `admin.access` check, and the whole sync is rolled back when it would remove the acting
     * administrator's own access to the panel or to this action. Like every other write, it locks the
     * `admin` role before the user, so a concurrent change of the same user from the console waits for
     * it instead of deadlocking.
     *
     * @param  list<AdminRole>  $adminRoles
     *
     * @throws AdminAuthorizationException
     */
    public function syncUserRoles(User $user, array $adminRoles, User $actor): AdminAuthorizationChanges
    {
        return $this->syncWithinTransaction(function () use ($user, $adminRoles, $actor): AdminAuthorizationChanges {
            $this->lockCatalogRole(AdminRole::Admin);
            $this->lockUser($user);

            $currentRoles = $user->roles
                ->filter(static fn (Role $role): bool => $role->guard_name === self::GUARD)
                ->map(static fn (Role $role): ?AdminRole => AdminRole::tryFrom($role->name))
                ->filter()
                ->values()
                ->all();

            $granted = [];
            $revoked = [];

            foreach (AdminRole::cases() as $adminRole) {
                if (\in_array($adminRole, $adminRoles, true) && ! \in_array($adminRole, $currentRoles, true)) {
                    $this->grantRole($user, $adminRole, $actor);
                    $granted[] = $adminRole->value;
                }
            }

            foreach (AdminRole::cases() as $adminRole) {
                if (\in_array($adminRole, $currentRoles, true) && ! \in_array($adminRole, $adminRoles, true)) {
                    $this->revokeRole($user, $adminRole, $actor);
                    $revoked[] = $adminRole->value;
                }
            }

            $this->ensureActorKeepsAccess($actor, AdminPermission::AdminUsersRoles);

            return new AdminAuthorizationChanges($granted, $revoked);
        });
    }

    /**
     * Leave the cataloged role with exactly the selected cataloged permissions, in one transaction.
     * Permissions outside the catalog are kept, and the whole sync is rolled back when it would remove
     * the acting administrator's own access to the panel or to this action.
     *
     * @param  list<AdminPermission>  $adminPermissions
     *
     * @throws AdminAuthorizationException
     */
    public function syncRolePermissions(AdminRole $adminRole, array $adminPermissions, User $user): AdminAuthorizationChanges
    {
        return $this->syncWithinTransaction(function () use ($adminRole, $adminPermissions, $user): AdminAuthorizationChanges {
            ['role' => $role] = $this->lockCatalogRole($adminRole);

            $currentPermissions = Permission::query()
                ->where('guard_name', self::GUARD)
                ->whereHas('roles', fn (Builder $builder): Builder => $builder->whereKey($role->getKey()))
                ->get()
                ->map(static fn (Permission $permission): ?AdminPermission => AdminPermission::tryFrom($permission->name))
                ->filter()
                ->values()
                ->all();

            $granted = [];
            $revoked = [];

            foreach (AdminPermission::cases() as $adminPermission) {
                if (\in_array($adminPermission, $adminPermissions, true) && ! \in_array($adminPermission, $currentPermissions, true)) {
                    $this->grantPermission($adminRole, $adminPermission, $user);
                    $granted[] = $adminPermission->value;
                }
            }

            foreach (AdminPermission::cases() as $adminPermission) {
                if (\in_array($adminPermission, $currentPermissions, true) && ! \in_array($adminPermission, $adminPermissions, true)) {
                    $this->revokePermission($adminRole, $adminPermission, $user);
                    $revoked[] = $adminPermission->value;
                }
            }

            $this->ensureActorKeepsAccess($user, AdminPermission::AdminRolesUpdate);

            return new AdminAuthorizationChanges($granted, $revoked);
        });
    }

    /**
     * Run a sync in one transaction and flush Spatie's cache afterwards whatever the outcome: the
     * actor check may rebuild it from rows that a failed sync then rolls back.
     *
     * @param  Closure(): AdminAuthorizationChanges  $sync
     *
     * @throws AdminAuthorizationException
     */
    private function syncWithinTransaction(Closure $sync): AdminAuthorizationChanges
    {
        try {
            return DB::transaction($sync);
        } finally {
            $this->permissionRegistrar->forgetCachedPermissions();
        }
    }

    /**
     * @throws AdminAuthorizationException when the actor no longer holds `admin.access` or the permission
     *                                     that authorized the sync
     */
    private function ensureActorKeepsAccess(User $user, AdminPermission $adminPermission): void
    {
        $this->permissionRegistrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');

        throw_unless(
            $user->hasPermissionTo(AdminPermission::AdminAccess->value, self::GUARD)
                && $user->hasPermissionTo($adminPermission->value, self::GUARD),
            AdminAuthorizationException::class,
            AdminAuthorizationFailure::ActorWouldLoseAccess,
        );
    }

    /**
     * Lock the `admin` role and the `admin.access` permission together with the requested role, in
     * that order, so concurrent changes of the catalog are serialized.
     *
     * @return array{role: Role, adminAccessPermission: Permission}
     *
     * @throws AdminAuthorizationException
     */
    private function lockCatalogRole(AdminRole $adminRole): array
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

        throw_if(! $adminRoleLock instanceof Role || ! $adminAccessPermission instanceof Permission, AdminAuthorizationException::class, AdminAuthorizationFailure::CatalogNotInitialized);

        $role = $adminRole === AdminRole::Admin
            ? $adminRoleLock
            : Role::query()
                ->where('name', $adminRole->value)
                ->where('guard_name', self::GUARD)
                ->lockForUpdate()
                ->first();

        throw_unless($role instanceof Role, AdminAuthorizationException::class, AdminAuthorizationFailure::RoleNotInitialized);

        return ['role' => $role, 'adminAccessPermission' => $adminAccessPermission];
    }

    /**
     * @throws AdminAuthorizationException
     */
    private function catalogPermission(AdminPermission $adminPermission, Permission $adminAccessPermission): Permission
    {
        if ($adminPermission === AdminPermission::AdminAccess) {
            return $adminAccessPermission;
        }

        $permission = Permission::query()
            ->where('name', $adminPermission->value)
            ->where('guard_name', self::GUARD)
            ->first();

        throw_unless($permission instanceof Permission, AdminAuthorizationException::class, AdminAuthorizationFailure::PermissionNotInitialized);

        return $permission;
    }

    /**
     * Lock the user's row and drop its cached roles and permissions, so the checks read what is stored.
     */
    private function lockUser(User $user): void
    {
        User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * Whether removing the role would leave nobody with `admin.access`: the user keeps it through no
     * other grant and no other user has it.
     */
    private function isLastAdminAccessHolderWithout(User $user, Role $revokedRole, Permission $adminAccessPermission): bool
    {
        return ! $this->userRetainsAdminAccessAfterRevocation($user, $revokedRole, $adminAccessPermission)
            && ! User::query()
                ->permission(AdminPermission::AdminAccess->value)
                ->whereKeyNot($user->getKey())
                ->exists();
    }

    private function userRetainsAdminAccessAfterRevocation(User $user, Role $revokedRole, Permission $adminAccessPermission): bool
    {
        if ($user->hasDirectPermission($adminAccessPermission)) {
            return true;
        }

        return $user->roles()
            ->whereKeyNot($revokedRole->getKey())
            ->whereHas(
                'permissions',
                fn (Builder $builder): Builder => $builder->whereKey($adminAccessPermission->getKey()),
            )
            ->exists();
    }

    private function aUserRetainsAdminAccessAfterRevocation(Role $revokedRole, Permission $adminAccessPermission): bool
    {
        if (User::query()
            ->whereHas(
                'permissions',
                fn (Builder $builder): Builder => $builder->whereKey($adminAccessPermission->getKey()),
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
                        fn (Builder $builder): Builder => $builder->whereKey($adminAccessPermission->getKey()),
                    ),
            )
            ->exists();
    }

    /**
     * Spatie flushes its cache when a permission changes, but a request running in parallel could
     * rebuild it from rows that are not committed yet; flushing again after the commit closes that gap.
     */
    private function forgetCachedPermissionsAfterCommit(): void
    {
        DB::afterCommit(fn (): bool => $this->permissionRegistrar->forgetCachedPermissions());
    }
}
