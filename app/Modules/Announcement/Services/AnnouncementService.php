<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Services;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Announcement\Data\AnnouncementData;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Creates, changes, unpublishes, duplicates and deletes announcements, auditing each change in the
 * admin activity log without its title or body. Authorization belongs to AnnouncementPolicy and is
 * applied by the caller.
 */
final readonly class AnnouncementService
{
    /** @var list<string> */
    private const array CONTENT_ATTRIBUTES = [
        'title',
        'body',
        'type',
        'audience',
        'display',
        'button_label',
        'button_url',
        'is_dismissible',
    ];

    public function __construct(
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Create an announcement authored by the given administrator.
     */
    public function create(AnnouncementData $announcementData, User $user): Announcement
    {
        return DB::transaction(function () use ($announcementData, $user): Announcement {
            $announcement = Announcement::query()->create([
                ...$announcementData->toModelAttributes(),
                'created_by' => $user->id,
            ]);

            $this->activityLogger->record(ActivityLogAdminEvent::AnnouncementCreated, $announcement, $user);

            return $announcement;
        });
    }

    /**
     * Submit a draft or rejected announcement for review and record who submitted it.
     */
    public function submitForReview(Announcement $announcement, User $user): Announcement
    {
        return DB::transaction(function () use ($announcement, $user): Announcement {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->whereKey($announcement->getKey())
                ->firstOrFail();

            $reviewAction = match ($lockedAnnouncement->status) {
                AnnouncementStatus::Draft => AnnouncementReviewAction::Submitted,
                AnnouncementStatus::Rejected => AnnouncementReviewAction::Resubmitted,
                default => throw new LogicException('Only draft and rejected announcements may be submitted for review.'),
            };

            $lockedAnnouncement->forceFill(['status' => AnnouncementStatus::InReview])->save();

            $lockedAnnouncement->reviews()->create([
                'action' => $reviewAction,
                'reviewed_by' => $user->id,
            ]);

            $this->activityLogger->record(ActivityLogAdminEvent::AnnouncementSubmitted, $lockedAnnouncement, $user);

            return $lockedAnnouncement;
        });
    }

    /**
     * Approve an announcement when the reviewer has seen its latest submission.
     */
    public function approve(
        Announcement $announcement,
        User $user,
        int $expectedSubmissionReviewId,
        ?string $comment = null,
    ): Announcement {
        return $this->recordReviewDecision(
            $announcement,
            $user,
            $expectedSubmissionReviewId,
            AnnouncementStatus::Approved,
            AnnouncementReviewAction::Approved,
            ActivityLogAdminEvent::AnnouncementApproved,
            $comment,
        );
    }

    /**
     * Reject an announcement with a required comment of at most 1000 characters.
     */
    public function reject(
        Announcement $announcement,
        User $user,
        int $expectedSubmissionReviewId,
        string $comment,
    ): Announcement {
        throw_if(trim($comment) === '', InvalidArgumentException::class, 'A rejection comment is required.');

        throw_if(mb_strlen($comment) > 1000, InvalidArgumentException::class, 'A rejection comment may not exceed 1000 characters.');

        return $this->recordReviewDecision(
            $announcement,
            $user,
            $expectedSubmissionReviewId,
            AnnouncementStatus::Rejected,
            AnnouncementReviewAction::Rejected,
            ActivityLogAdminEvent::AnnouncementRejected,
            $comment,
        );
    }

    /**
     * Apply content and schedule; content changes to approved announcements start a new review.
     */
    public function update(Announcement $announcement, AnnouncementData $announcementData, User $user): Announcement
    {
        return DB::transaction(function () use ($announcement, $announcementData, $user): Announcement {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->whereKey($announcement->getKey())
                ->firstOrFail();
            $lockedAnnouncement->fill($announcementData->toModelAttributes());

            $changedAttributes = array_keys($lockedAnnouncement->getDirty());

            if ($changedAttributes === []) {
                return $lockedAnnouncement;
            }

            $hasContentChanges = array_intersect($changedAttributes, self::CONTENT_ATTRIBUTES) !== [];
            $requiresReview = $lockedAnnouncement->status === AnnouncementStatus::Approved && $hasContentChanges;

            if ($requiresReview) {
                $lockedAnnouncement->forceFill(['status' => AnnouncementStatus::InReview]);
            }

            $lockedAnnouncement->save();

            if ($requiresReview) {
                $lockedAnnouncement->reviews()->create([
                    'action' => AnnouncementReviewAction::Resubmitted,
                    'reviewed_by' => $user->id,
                ]);
            }

            $this->activityLogger->record(
                ActivityLogAdminEvent::AnnouncementUpdated,
                $lockedAnnouncement,
                $user,
                ['changed_attributes' => $changedAttributes],
            );

            if ($requiresReview) {
                $this->activityLogger->record(ActivityLogAdminEvent::AnnouncementSubmitted, $lockedAnnouncement, $user);
            }

            return $lockedAnnouncement;
        });
    }

    /**
     * Resubmit an approved announcement when its image changes after the form data is saved.
     */
    public function resubmitApprovedForImageChange(Announcement $announcement, User $user): Announcement
    {
        return DB::transaction(function () use ($announcement, $user): Announcement {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->whereKey($announcement->getKey())
                ->firstOrFail();

            if ($lockedAnnouncement->status !== AnnouncementStatus::Approved) {
                return $lockedAnnouncement;
            }

            $lockedAnnouncement->forceFill(['status' => AnnouncementStatus::InReview])->save();

            $lockedAnnouncement->reviews()->create([
                'action' => AnnouncementReviewAction::Resubmitted,
                'reviewed_by' => $user->id,
            ]);

            $this->activityLogger->record(
                ActivityLogAdminEvent::AnnouncementUpdated,
                $lockedAnnouncement,
                $user,
                ['changed_attributes' => ['image']],
            );

            $this->activityLogger->record(ActivityLogAdminEvent::AnnouncementSubmitted, $lockedAnnouncement, $user);

            return $lockedAnnouncement;
        });
    }

    /**
     * End a running announcement now, so it stops showing right away.
     *
     * @throws LogicException when the announcement has not started or already ended
     */
    public function unpublish(Announcement $announcement, User $user): Announcement
    {
        DB::transaction(function () use ($announcement, $user): void {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->whereKey($announcement->getKey())
                ->firstOrFail();

            throw_unless(
                $lockedAnnouncement->status === AnnouncementStatus::Approved,
                LogicException::class,
                'Only approved announcements may be unpublished.',
            );

            $now = now();

            throw_if($lockedAnnouncement->starts_at->isAfter($now), LogicException::class, 'The announcement has not started yet.');

            throw_if(
                $lockedAnnouncement->ends_at !== null && ! $lockedAnnouncement->ends_at->isAfter($now),
                LogicException::class,
                'The announcement already ended.',
            );

            $lockedAnnouncement->forceFill(['ends_at' => $now])->save();

            $this->activityLogger->record(ActivityLogAdminEvent::AnnouncementUnpublished, $lockedAnnouncement, $user);
        });

        return $announcement->refresh();
    }

    /**
     * Copy an announcement and its image as a new one scheduled for tomorrow and without end date,
     * so the copy is never shown before someone reviews it.
     */
    public function duplicate(Announcement $announcement, User $user): Announcement
    {
        return DB::transaction(function () use ($announcement, $user): Announcement {
            $copy = $announcement->replicate(['created_at', 'updated_at']);

            $copy->forceFill([
                'starts_at' => now()->addDay(),
                'ends_at' => null,
                'status' => AnnouncementStatus::Draft,
                'created_by' => $user->id,
            ])->save();

            $announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)
                ?->copy($copy, Announcement::IMAGE_MEDIA_COLLECTION);

            $this->activityLogger->record(
                ActivityLogAdminEvent::AnnouncementDuplicated,
                $copy,
                $user,
                ['source_announcement_id' => $announcement->id],
            );

            return $copy;
        });
    }

    /**
     * Delete an announcement with its image and dismissals; the audit keeps its id.
     */
    public function delete(Announcement $announcement, User $user): void
    {
        DB::transaction(function () use ($announcement, $user): void {
            $this->activityLogger->record(ActivityLogAdminEvent::AnnouncementDeleted, $announcement, $user);

            $announcement->delete();
        });
    }

    /**
     * Lock the announcement and latest submission before persisting a review decision.
     */
    private function recordReviewDecision(
        Announcement $announcement,
        User $user,
        int $expectedSubmissionReviewId,
        AnnouncementStatus $announcementStatus,
        AnnouncementReviewAction $announcementReviewAction,
        ActivityLogAdminEvent $activityLogAdminEvent,
        ?string $comment,
    ): Announcement {
        return DB::transaction(function () use (
            $announcement,
            $user,
            $expectedSubmissionReviewId,
            $announcementStatus,
            $announcementReviewAction,
            $activityLogAdminEvent,
            $comment,
        ): Announcement {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->whereKey($announcement->getKey())
                ->firstOrFail();

            throw_if($lockedAnnouncement->status !== AnnouncementStatus::InReview, LogicException::class, 'Only announcements in review may be approved or rejected.');

            $latestSubmission = AnnouncementReview::query()
                ->where('announcement_id', $lockedAnnouncement->getKey())
                ->whereIn('action', [
                    AnnouncementReviewAction::Submitted->value,
                    AnnouncementReviewAction::Resubmitted->value,
                ])
                ->latest()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            throw_unless($latestSubmission instanceof AnnouncementReview, LogicException::class, 'The announcement has no submission to review.');

            throw_if($latestSubmission->id !== $expectedSubmissionReviewId, LogicException::class, 'The announcement submission changed before it was reviewed.');

            throw_if($lockedAnnouncement->created_by === $user->id, LogicException::class, 'The announcement author may not review their own announcement.');

            throw_if($latestSubmission->reviewed_by === $user->id, LogicException::class, 'The latest submitter may not review their own submission.');

            $lockedAnnouncement->forceFill(['status' => $announcementStatus])->save();

            $lockedAnnouncement->reviews()->create([
                'action' => $announcementReviewAction,
                'comment' => $comment,
                'reviewed_by' => $user->id,
            ]);

            $this->activityLogger->record($activityLogAdminEvent, $lockedAnnouncement, $user);

            return $lockedAnnouncement;
        });
    }
}
