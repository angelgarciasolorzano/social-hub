<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\User\Models\User;

/**
 * The activity log is read-only: administrators with the activity permissions can
 * list and open records, and nobody can create, change or delete them.
 */
final class ActivityLogPolicy
{
    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminActivityViewAny->value);
    }

    public function view(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminActivityView->value);
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
