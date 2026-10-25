<?php

declare(strict_types=1);

namespace App\User\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\User\Models\User;

final class UserPolicy
{
    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminUsersViewAny->value);
    }

    public function view(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminUsersView->value);
    }

    /**
     * Suspend another active account; an administrator never suspends their own.
     */
    public function suspend(User $authenticatedUser, User $user): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminUsersSuspend->value)
            && $authenticatedUser->isNot($user)
            && ! $user->isSuspended();
    }

    /**
     * Reactivate a suspended account.
     */
    public function reactivate(User $authenticatedUser, User $user): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminUsersReactivate->value)
            && $user->isSuspended();
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
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

    public function forceDelete(): bool
    {
        return false;
    }
}
