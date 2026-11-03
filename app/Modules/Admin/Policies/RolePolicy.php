<?php

declare(strict_types=1);

namespace App\Modules\Admin\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Admin roles in the panel: administrators with the roles permissions can list and open them, and the
 * roles manager can change the permissions of a cataloged role of the `admin` guard. The catalog is
 * closed, so nobody creates, deletes or restores a role from the panel.
 *
 * Registered with `Gate::policy()` in `AdminServiceProvider`, because the model belongs to Spatie.
 */
final class RolePolicy
{
    private const string GUARD = 'admin';

    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesViewAny->value);
    }

    public function view(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesView->value);
    }

    /**
     * Change the permissions of a cataloged role; a role outside `AdminRole` or of another guard is
     * listed but never editable.
     */
    public function update(User $authenticatedUser, Role $role): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminRolesUpdate->value)
            && $role->guard_name === self::GUARD
            && AdminRole::tryFrom($role->name) instanceof AdminRole;
    }

    public function create(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
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

    public function replicate(): bool
    {
        return false;
    }

    public function reorder(): bool
    {
        return false;
    }
}
