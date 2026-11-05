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
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The only writer of the Admin authorization catalog outside the seeders: creates, edits, duplicates
 * and deletes custom roles, and grants and revokes the roles of the `admin` guard, system or custom, and
 * their cataloged permissions, for the console commands and the panel. Each change locks the `admin` role, the `admin.access` permission and the affected rows,
 * never changes the permissions of the locked `admin` role, never leaves a role with a permission
 * whose requirements it lacks, never leaves the panel without a user holding `admin.access`, and
 * records the activity only when something changed.
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
    public function lockCatalog(Role $role): void
    {
        $this->lockCatalogRole($role);
    }

    /**
     * Assign a role of the `admin` guard to the user; returns false when the user already had it.
     *
     * @throws AdminAuthorizationException
     */
    public function grantRole(User $user, Role $role, User $actor): bool
    {
        return DB::transaction(function () use ($user, $role, $actor): bool {
            ['role' => $lockedRole] = $this->lockCatalogRole($role);

            $this->lockUser($user);

            if ($user->hasRole($lockedRole)) {
                return false;
            }

            $user->assignRole($lockedRole);

            $this->activityLogger->record(ActivityLogAdminEvent::RoleGranted, $user, $actor, ['role' => $lockedRole->name]);
            $this->forgetCachedPermissionsAfterCommit();

            return true;
        });
    }

    /**
     * Remove a role of the `admin` guard from the user; returns false when the user did not have it.
     *
     * @throws AdminAuthorizationException when it would leave no user with `admin.access`
     */
    public function revokeRole(User $user, Role $role, User $actor): bool
    {
        return DB::transaction(function () use ($user, $role, $actor): bool {
            ['role' => $lockedRole, 'adminAccessPermission' => $adminAccessPermission] = $this->lockCatalogRole($role);

            $this->lockUser($user);

            if (! $user->hasRole($lockedRole)) {
                return false;
            }

            $roleGrantsAdminAccess = $lockedRole->permissions()
                ->whereKey($adminAccessPermission->getKey())
                ->exists();

            throw_if(
                $roleGrantsAdminAccess && $this->isLastAdminAccessHolderWithout($user, $lockedRole, $adminAccessPermission),
                AdminAuthorizationException::class,
                AdminAuthorizationFailure::LastAdminAccess,
            );

            $user->removeRole($lockedRole);

            $this->activityLogger->record(ActivityLogAdminEvent::RoleRevoked, $user, $actor, ['role' => $lockedRole->name]);
            $this->forgetCachedPermissionsAfterCommit();

            return true;
        });
    }

    /**
     * Grant a cataloged permission to a role; returns false when the role already had it.
     *
     * @throws AdminAuthorizationException when the role is locked or lacks a requirement of the permission
     */
    public function grantPermission(Role $role, AdminPermission $adminPermission, User $user): bool
    {
        return DB::transaction(function () use ($role, $adminPermission, $user): bool {
            ['role' => $lockedRole, 'adminAccessPermission' => $adminAccessPermission] = $this->lockEditableRole($role);

            $permission = $this->catalogPermission($adminPermission, $adminAccessPermission);
            $currentPermissions = $this->catalogPermissionsOf($lockedRole);

            if (\in_array($adminPermission, $currentPermissions, true)) {
                return false;
            }

            $this->ensureRequirementsMet([...$currentPermissions, $adminPermission]);

            $this->applyPermissionGrant($lockedRole, $adminPermission, $permission, $user);

            return true;
        });
    }

    /**
     * Revoke a cataloged permission from a role; returns false when the role did not have it.
     *
     * @throws AdminAuthorizationException when the role is locked, another of its permissions requires
     *                                     this one, or revoking `admin.access` would leave no user with it
     */
    public function revokePermission(Role $role, AdminPermission $adminPermission, User $user): bool
    {
        return DB::transaction(function () use ($role, $adminPermission, $user): bool {
            ['role' => $lockedRole, 'adminAccessPermission' => $adminAccessPermission] = $this->lockEditableRole($role);

            $permission = $this->catalogPermission($adminPermission, $adminAccessPermission);
            $currentPermissions = $this->catalogPermissionsOf($lockedRole);

            if (! \in_array($adminPermission, $currentPermissions, true)) {
                return false;
            }

            $this->ensureRequirementsMet(array_values(array_filter(
                $currentPermissions,
                static fn (AdminPermission $currentPermission): bool => $currentPermission !== $adminPermission,
            )));

            $this->applyPermissionRevocation($lockedRole, $adminPermission, $permission, $adminAccessPermission, $user);

            return true;
        });
    }

    /**
     * Leave the user with exactly the selected roles of the `admin` guard, system or custom, in one
     * transaction. Roles of other guards are kept. Grants run before revocations, so moving access
     * between roles never trips the last `admin.access` check, and the whole sync is rolled back when it
     * would remove the acting administrator's own access to the panel or to this action. Like every
     * other write, it locks the `admin` role before the user, so a concurrent change of the same user
     * from the console waits for it instead of deadlocking.
     *
     * @param  list<Role>  $roles
     *
     * @throws AdminAuthorizationException
     */
    public function syncUserRoles(User $user, array $roles, User $actor): AdminAuthorizationChanges
    {
        return $this->syncWithinTransaction(function () use ($user, $roles, $actor): AdminAuthorizationChanges {
            $this->lockCatalogRoot();
            $this->lockUser($user);

            /** @var array<int, Role> $selectedRoles */
            $selectedRoles = [];

            foreach ($roles as $role) {
                $selectedRoles[$role->id] = $role;
            }

            ksort($selectedRoles);

            /** @var array<int, Role> $currentRoles */
            $currentRoles = [];

            foreach ($user->roles as $currentRole) {
                if ($currentRole->guard_name === self::GUARD) {
                    $currentRoles[$currentRole->id] = $currentRole;
                }
            }

            ksort($currentRoles);

            $granted = [];
            $revoked = [];

            foreach (array_diff_key($selectedRoles, $currentRoles) as $role) {
                $this->grantRole($user, $role, $actor);
                $granted[] = $role->name;
            }

            foreach (array_diff_key($currentRoles, $selectedRoles) as $role) {
                $this->revokeRole($user, $role, $actor);
                $revoked[] = $role->name;
            }

            $this->ensureActorKeepsAccess($actor, AdminPermission::AdminUsersRoles);

            return new AdminAuthorizationChanges($granted, $revoked);
        });
    }

    /**
     * Leave the role with exactly the selected cataloged permissions, in one transaction. Permissions
     * outside the catalog are kept. The selection must bring every requirement of its permissions, the
     * locked `admin` role cannot be changed, and the whole sync is rolled back when it would remove the
     * acting administrator's own access to the panel or to this action.
     *
     * @param  list<AdminPermission>  $adminPermissions
     *
     * @throws AdminAuthorizationException
     */
    public function syncRolePermissions(Role $role, array $adminPermissions, User $user): AdminAuthorizationChanges
    {
        return $this->syncWithinTransaction(function () use ($role, $adminPermissions, $user): AdminAuthorizationChanges {
            ['role' => $lockedRole, 'adminAccessPermission' => $adminAccessPermission] = $this->lockEditableRole($role);

            $this->ensureRequirementsMet($adminPermissions);

            $currentPermissions = $this->catalogPermissionsOf($lockedRole);

            $granted = [];
            $revoked = [];

            foreach (AdminPermission::cases() as $adminPermission) {
                if (\in_array($adminPermission, $adminPermissions, true) && ! \in_array($adminPermission, $currentPermissions, true)) {
                    $permission = $this->catalogPermission($adminPermission, $adminAccessPermission);
                    $this->applyPermissionGrant($lockedRole, $adminPermission, $permission, $user);
                    $granted[] = $adminPermission->value;
                }
            }

            foreach (AdminPermission::cases() as $adminPermission) {
                if (\in_array($adminPermission, $currentPermissions, true) && ! \in_array($adminPermission, $adminPermissions, true)) {
                    $permission = $this->catalogPermission($adminPermission, $adminAccessPermission);
                    $this->applyPermissionRevocation($lockedRole, $adminPermission, $permission, $adminAccessPermission, $user);
                    $revoked[] = $adminPermission->value;
                }
            }

            $this->ensureActorKeepsAccess($user, AdminPermission::AdminRolesUpdate);

            return new AdminAuthorizationChanges($granted, $revoked);
        });
    }

    /**
     * Create a custom role with the given texts and cataloged permissions. Its stored name is
     * `custom-` plus the slug of the label, with a numeric suffix when that name is taken.
     *
     * @param  list<AdminPermission>  $adminPermissions
     *
     * @throws AdminAuthorizationException when the label is taken or the permissions lack a requirement
     * @throws InvalidArgumentException when a text is blank or too long
     */
    public function createRole(string $label, ?string $description, array $adminPermissions, User $user): Role
    {
        $label = $this->normalizedLabel($label);
        $description = $this->normalizedDescription($description);

        return DB::transaction(function () use ($label, $description, $adminPermissions, $user): Role {
            ['adminAccessPermission' => $adminAccessPermission] = $this->lockCatalogRoot();

            $this->ensureLabelAvailable($label);
            $this->ensureRequirementsMet($adminPermissions);

            $selectedPermissions = $this->inCatalogOrder($adminPermissions);
            $permissions = array_map(
                fn (AdminPermission $adminPermission): Permission => $this->catalogPermission($adminPermission, $adminAccessPermission),
                $selectedPermissions,
            );

            $role = $this->newCustomRole($label, $description);
            $role->givePermissionTo($permissions);

            $this->activityLogger->record(ActivityLogAdminEvent::RoleCreated, $role, $user, [
                'role' => $role->name,
                'permissions' => array_map(static fn (AdminPermission $adminPermission): string => $adminPermission->value, $selectedPermissions),
            ]);

            $this->forgetCachedPermissionsAfterCommit();

            return $role;
        });
    }

    /**
     * Change the label and description of a custom role; its stored name never changes. Returns false
     * when nothing changed.
     *
     * @throws AdminAuthorizationException when the role is a system role or the label is taken
     * @throws InvalidArgumentException when a text is blank or too long
     */
    public function updateRoleDetails(Role $role, string $label, ?string $description, User $user): bool
    {
        $label = $this->normalizedLabel($label);
        $description = $this->normalizedDescription($description);

        return DB::transaction(function () use ($role, $label, $description, $user): bool {
            ['role' => $lockedRole] = $this->lockCatalogRole($role);

            throw_if($lockedRole->isSystem(), AdminAuthorizationException::class, AdminAuthorizationFailure::SystemRoleProtected);

            $changedAttributes = [];

            if ($lockedRole->label !== $label) {
                $this->ensureLabelAvailable($label, $lockedRole);
                $changedAttributes[] = 'label';
            }

            if ($lockedRole->description !== $description) {
                $changedAttributes[] = 'description';
            }

            if ($changedAttributes === []) {
                return false;
            }

            $lockedRole->update(['label' => $label, 'description' => $description]);

            $this->activityLogger->record(ActivityLogAdminEvent::RoleUpdated, $lockedRole, $user, [
                'role' => $lockedRole->name,
                'changed_attributes' => $changedAttributes,
            ]);

            return true;
        });
    }

    /**
     * Create a custom role with the given label and the description and cataloged permissions of the
     * source role, system or custom.
     *
     * @throws AdminAuthorizationException when the source no longer exists or the label is taken
     * @throws InvalidArgumentException when the label is blank or too long
     */
    public function duplicateRole(Role $source, string $label, User $user): Role
    {
        $label = $this->normalizedLabel($label);

        return DB::transaction(function () use ($source, $label, $user): Role {
            ['role' => $lockedSource] = $this->lockCatalogRole($source);

            $this->ensureLabelAvailable($label);

            $role = $this->newCustomRole($label, $this->normalizedDescription($lockedSource->displayDescription()));
            $role->givePermissionTo($lockedSource->permissions);

            $this->activityLogger->record(ActivityLogAdminEvent::RoleDuplicated, $role, $user, [
                'role' => $role->name,
                'source_role_id' => $lockedSource->id,
            ]);

            $this->forgetCachedPermissionsAfterCommit();

            return $role;
        });
    }

    /**
     * Delete a custom role nobody holds, together with its permission grants.
     *
     * @throws AdminAuthorizationException when the role is a system role or still has users
     */
    public function deleteRole(Role $role, User $user): void
    {
        DB::transaction(function () use ($role, $user): void {
            ['role' => $lockedRole] = $this->lockCatalogRole($role);

            throw_if($lockedRole->isSystem(), AdminAuthorizationException::class, AdminAuthorizationFailure::SystemRoleProtected);
            throw_if($lockedRole->users()->exists(), AdminAuthorizationException::class, AdminAuthorizationFailure::RoleHasUsers);

            $this->activityLogger->record(ActivityLogAdminEvent::RoleDeleted, $lockedRole, $user, ['role' => $lockedRole->name]);

            $lockedRole->delete();

            $this->forgetCachedPermissionsAfterCommit();
        });
    }

    /**
     * Store a custom role of the `admin` guard under the first free name derived from its label.
     */
    private function newCustomRole(string $label, ?string $description): Role
    {
        $baseName = Role::CUSTOM_NAME_PREFIX.(Str::slug($label) !== '' ? Str::slug($label) : 'role');
        $name = $baseName;
        $suffix = 1;

        while (Role::query()->where('name', $name)->where('guard_name', self::GUARD)->exists()) {
            $suffix++;
            $name = "{$baseName}-{$suffix}";
        }

        return Role::query()->create([
            'name' => $name,
            'guard_name' => self::GUARD,
            'is_system' => false,
            'label' => $label,
            'description' => $description,
        ]);
    }

    /**
     * @throws AdminAuthorizationException when another role of the `admin` guard has the label, ignoring case
     */
    private function ensureLabelAvailable(string $label, ?Role $exceptRole = null): void
    {
        $labelTaken = Role::query()
            ->where('guard_name', self::GUARD)
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->when($exceptRole instanceof Role, fn (Builder $builder): Builder => $builder->whereKeyNot($exceptRole?->getKey()))
            ->exists();

        throw_if($labelTaken, AdminAuthorizationException::class, AdminAuthorizationFailure::RoleLabelTaken);
    }

    /**
     * @throws InvalidArgumentException when the label is blank or longer than the column allows
     */
    private function normalizedLabel(string $label): string
    {
        $label = trim($label);

        if ($label === '' || mb_strlen($label) > Role::LABEL_MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('A role label must have between 1 and %d characters.', Role::LABEL_MAX_LENGTH));
        }

        return $label;
    }

    /**
     * @throws InvalidArgumentException when the description is longer than the column allows
     */
    private function normalizedDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        if (mb_strlen($description) > Role::DESCRIPTION_MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('A role description must have at most %d characters.', Role::DESCRIPTION_MAX_LENGTH));
        }

        return $description === '' ? null : $description;
    }

    /**
     * @param  list<AdminPermission>  $adminPermissions
     * @return list<AdminPermission>
     */
    private function inCatalogOrder(array $adminPermissions): array
    {
        return array_values(array_filter(
            AdminPermission::cases(),
            static fn (AdminPermission $adminPermission): bool => \in_array($adminPermission, $adminPermissions, true),
        ));
    }

    private function applyPermissionGrant(Role $role, AdminPermission $adminPermission, Permission $permission, User $user): void
    {
        $role->givePermissionTo($permission);

        $this->activityLogger->record(ActivityLogAdminEvent::RolePermissionGranted, $role, $user, [
            'role' => $role->name,
            'permission' => $adminPermission->value,
        ]);

        $this->forgetCachedPermissionsAfterCommit();
    }

    /**
     * @throws AdminAuthorizationException when revoking `admin.access` would leave no user with it
     */
    private function applyPermissionRevocation(
        Role $role,
        AdminPermission $adminPermission,
        Permission $permission,
        Permission $adminAccessPermission,
        User $user,
    ): void {
        throw_if(
            $adminPermission === AdminPermission::AdminAccess
                && ! $this->aUserRetainsAdminAccessAfterRevocation($role, $adminAccessPermission),
            AdminAuthorizationException::class,
            AdminAuthorizationFailure::LastAdminAccess,
        );

        $role->revokePermissionTo($permission);

        $this->activityLogger->record(ActivityLogAdminEvent::RolePermissionRevoked, $role, $user, [
            'role' => $role->name,
            'permission' => $adminPermission->value,
        ]);

        $this->forgetCachedPermissionsAfterCommit();
    }

    /**
     * @param  list<AdminPermission>  $adminPermissions
     *
     * @throws AdminAuthorizationException when a permission of the set lacks one of its requirements
     */
    private function ensureRequirementsMet(array $adminPermissions): void
    {
        throw_unless(
            AdminPermission::missingRequirements($adminPermissions) === [],
            AdminAuthorizationException::class,
            AdminAuthorizationFailure::PermissionDependencyMissing,
        );
    }

    /**
     * The cataloged permissions the role holds; permissions outside the catalog are left out.
     *
     * @return list<AdminPermission>
     */
    private function catalogPermissionsOf(Role $role): array
    {
        $adminPermissions = [];

        $permissions = Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereHas('roles', fn (Builder $builder): Builder => $builder->whereKey($role->getKey()))
            ->get();

        foreach ($permissions as $permission) {
            $adminPermission = AdminPermission::tryFrom($permission->name);

            if ($adminPermission instanceof AdminPermission) {
                $adminPermissions[] = $adminPermission;
            }
        }

        return $adminPermissions;
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
     * Lock the `admin` role and the `admin.access` permission, in that order, so concurrent changes of
     * the catalog are serialized.
     *
     * @return array{adminRole: Role, adminAccessPermission: Permission}
     *
     * @throws AdminAuthorizationException
     */
    private function lockCatalogRoot(): array
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

        return ['adminRole' => $adminRoleLock, 'adminAccessPermission' => $adminAccessPermission];
    }

    /**
     * Lock the catalog root and then the requested role of the `admin` guard by its id, reading it
     * again so a role deleted or changed meanwhile is not used from a stale instance.
     *
     * @return array{role: Role, adminAccessPermission: Permission}
     *
     * @throws AdminAuthorizationException
     */
    private function lockCatalogRole(Role $role): array
    {
        ['adminRole' => $adminRoleLock, 'adminAccessPermission' => $adminAccessPermission] = $this->lockCatalogRoot();

        $lockedRole = $adminRoleLock->is($role)
            ? $adminRoleLock
            : Role::query()
                ->where('guard_name', self::GUARD)
                ->whereKey($role->getKey())
                ->lockForUpdate()
                ->first();

        throw_unless($lockedRole instanceof Role, AdminAuthorizationException::class, AdminAuthorizationFailure::RoleNotInitialized);

        return ['role' => $lockedRole, 'adminAccessPermission' => $adminAccessPermission];
    }

    /**
     * Lock the role like {@see lockCatalogRole()} and refuse it when its permissions cannot change.
     *
     * @return array{role: Role, adminAccessPermission: Permission}
     *
     * @throws AdminAuthorizationException
     */
    private function lockEditableRole(Role $role): array
    {
        $lockedCatalogRole = $this->lockCatalogRole($role);

        throw_if($lockedCatalogRole['role']->isLocked(), AdminAuthorizationException::class, AdminAuthorizationFailure::RoleLocked);

        return $lockedCatalogRole;
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
