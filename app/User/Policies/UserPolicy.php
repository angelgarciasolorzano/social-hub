<?php

declare(strict_types=1);

namespace App\User\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\User\Models\User;

final class UserPolicy
{
    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::UsersViewAny->value);
    }

    public function view(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::UsersView->value);
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
