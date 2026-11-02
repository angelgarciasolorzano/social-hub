<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
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

    public function submit(User $authenticatedUser, Announcement $announcement): bool
    {
        return $this->update($authenticatedUser)
            && $announcement->created_by === $authenticatedUser->id
            && \in_array($announcement->status, [AnnouncementStatus::Draft, AnnouncementStatus::Rejected], true);
    }

    public function review(User $authenticatedUser, Announcement $announcement): bool
    {
        if (! $authenticatedUser->can(AdminPermission::AdminAnnouncementsReview->value)
            || $announcement->status !== AnnouncementStatus::InReview
            || $announcement->created_by === $authenticatedUser->id) {
            return false;
        }

        $latestSubmission = $announcement->reviews()
            ->reorder()
            ->whereIn('action', [
                AnnouncementReviewAction::Submitted->value,
                AnnouncementReviewAction::Resubmitted->value,
            ])
            ->latest()
            ->orderByDesc('id')
            ->first();

        return $latestSubmission instanceof AnnouncementReview
            && $latestSubmission->reviewed_by !== $authenticatedUser->id;
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
            && Announcement::query()->dismissibleBy($authenticatedUser)->whereKey($announcement->getKey())->exists();
    }
}
