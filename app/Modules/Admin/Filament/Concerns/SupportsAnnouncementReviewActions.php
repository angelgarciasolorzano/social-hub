<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use App\User\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;

trait SupportsAnnouncementReviewActions
{
    use ConstrainsAnnouncementSubmissionReview;

    private static function canSubmitAnnouncement(Announcement $announcement): bool
    {
        $administrator = Filament::auth()->user();

        return $administrator instanceof User
            && $administrator->can(AdminPermission::AdminAnnouncementsUpdate->value)
            && $announcement->created_by === $administrator->id
            && \in_array($announcement->status, [AnnouncementStatus::Draft, AnnouncementStatus::Rejected], true);
    }

    private static function canReviewAnnouncement(Announcement $announcement): bool
    {
        $administrator = Filament::auth()->user();

        if (! $administrator instanceof User
            || ! $administrator->can(AdminPermission::AdminAnnouncementsReview->value)
            || $announcement->status !== AnnouncementStatus::InReview
            || $announcement->created_by === $administrator->id) {
            return false;
        }

        $latestSubmissionReview = self::latestSubmissionReview($announcement);

        return $latestSubmissionReview instanceof AnnouncementReview
            && $latestSubmissionReview->reviewed_by !== $administrator->id;
    }

    private static function latestSubmissionReview(Announcement $announcement): ?AnnouncementReview
    {
        if (! $announcement->relationLoaded('reviews')) {
            $announcement->load([
                'reviews' => static function (Relation $reviewQuery): void {
                    self::constrainLatestSubmissionReview($reviewQuery);
                },
            ]);
        }

        return $announcement->reviews->first();
    }

    private static function reviewAdministrator(): User
    {
        $authenticatedUser = Filament::auth()->user();

        abort_unless($authenticatedUser instanceof User, 403);

        return $authenticatedUser;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function expectedSubmissionReviewId(array $data): int
    {
        $expectedSubmissionReviewId = filter_var($data['expected_submission_review_id'] ?? null, FILTER_VALIDATE_INT);

        abort_unless(\is_int($expectedSubmissionReviewId) && $expectedSubmissionReviewId > 0, 422);

        return $expectedSubmissionReviewId;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function optionalComment(array $data): ?string
    {
        $comment = $data['comment'] ?? null;

        abort_unless($comment === null || \is_string($comment), 422);

        return \is_string($comment) && trim($comment) !== '' ? $comment : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function requiredComment(array $data): string
    {
        $comment = $data['comment'] ?? null;

        abort_unless(\is_string($comment), 422);

        return $comment;
    }
}
