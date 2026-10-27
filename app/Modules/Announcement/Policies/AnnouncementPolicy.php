<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Announcement\Models\Announcement;
use App\User\Models\User;

/**
 * Administrators manage announcements from the Admin panel with the announcements permissions;
 * bulk deletion and restoring do not exist. In the application, a user may dismiss an
 * announcement that is dismissible and currently shown to them.
 */
final class AnnouncementPolicy
{
    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminAnnouncementsViewAny->value);
    }

    public function view(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminAnnouncementsView->value);
    }

    public function create(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminAnnouncementsCreate->value);
    }

    public function update(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminAnnouncementsUpdate->value);
    }

    /**
     * Duplicating creates a new announcement, so it needs the create permission.
     */
    public function replicate(User $authenticatedUser): bool
    {
        return $this->create($authenticatedUser);
    }

    public function delete(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminAnnouncementsDelete->value);
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

    /**
     * A user may dismiss a dismissible announcement that is running for their audience;
     * dismissing it again is allowed so a repeated request stays harmless.
     */
    public function dismiss(User $authenticatedUser, Announcement $announcement): bool
    {
        return $announcement->is_dismissible
            && Announcement::query()->availableTo($authenticatedUser)->whereKey($announcement->getKey())->exists();
    }
}
