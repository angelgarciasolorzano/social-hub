<?php

declare(strict_types=1);

namespace App\Modules\Admin\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Models\Role;
use App\User\Models\User;

/**
 * Admin roles in the panel. Administrators with the roles permissions list and open them; the roles
 * manager creates and duplicates custom roles, changes the permissions of any role of the `admin` guard
 * except the locked `admin` role, and renames and deletes custom roles only, since system roles come
 * from the catalog. Whether a role still has users is checked by the service and the panel, not here.
 * Nobody restores, force deletes or reorders a role.
 *
 * Attached to the model with `#[UsePolicy]`; `AdminServiceProvider` also registers it for Spatie's
 * role class until every caller uses the module's model.
 */
final class RolePolicy
{
    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesViewAny->value);
    }

    public function view(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesView->value);
    }

    public function create(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesCreate->value);
    }

    /**
     * Create a custom role from the description and permissions of a role of the `admin` guard.
     */
    public function replicate(User $authenticatedUser, Role $role): bool
    {
        return $this->create($authenticatedUser) && $this->isAdminGuardRole($role);
    }

    /**
     * Change the permissions of a role of the `admin` guard, system or custom, but never of `admin`.
     */
    public function update(User $authenticatedUser, Role $role): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesUpdate->value)
            && $this->isAdminGuardRole($role)
            && ! $role->isLocked();
    }

    /**
     * Change the label and description of a custom role.
     */
    public function updateDetails(User $authenticatedUser, Role $role): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesUpdate->value)
            && $this->isCustomRole($role);
    }

    /**
     * Delete a custom role; one that still has users is refused by the service.
     */
    public function delete(User $authenticatedUser, Role $role): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesDelete->value)
            && $this->isCustomRole($role);
    }

    public function deleteAny(): bool
    {
        return false;
    }

    public function restore(): bool
    {
        return false;
    }

    public function restoreAny(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }

    public function reorder(): bool
    {
        return false;
    }

    private function isAdminGuardRole(Role $role): bool
    {
        return $role->guard_name === Role::GUARD_NAME;
    }

    private function isCustomRole(Role $role): bool
    {
        return $this->isAdminGuardRole($role) && ! $role->isSystem();
    }
}
