<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Services;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Announcement\Data\AnnouncementData;
use App\Modules\Announcement\Models\Announcement;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Creates, changes, unpublishes, duplicates and deletes announcements, auditing each change in the
 * admin activity log without its title or body. Authorization belongs to AnnouncementPolicy and is
 * applied by the caller.
 */
final readonly class AnnouncementService
{
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
     * Apply the given content and schedule; saving without changes records nothing.
     */
    public function update(Announcement $announcement, AnnouncementData $announcementData, User $user): Announcement
    {
        $announcement->fill($announcementData->toModelAttributes());

        if (! $announcement->isDirty()) {
            return $announcement;
        }

        return DB::transaction(function () use ($announcement, $user): Announcement {
            $changedAttributes = array_keys($announcement->getDirty());

            $announcement->save();

            $this->activityLogger->record(
                ActivityLogAdminEvent::AnnouncementUpdated,
                $announcement,
                $user,
                ['changed_attributes' => $changedAttributes],
            );

            return $announcement;
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
            $lockedAnnouncement = Announcement::query()->lockForUpdate()->whereKey($announcement->id)->firstOrFail();

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
}
