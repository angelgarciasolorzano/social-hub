<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Listeners;

use App\Modules\ActivityLog\Enums\Events\ActivityLogLikeEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Like\Events\LikeToggled;

/**
 * Records likes added to and removed from posts and comments in the short-lived `likes` log.
 */
final readonly class LikeActivitySubscriber
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            LikeToggled::class => 'handleLikeToggled',
        ];
    }

    public function handleLikeToggled(LikeToggled $likeToggled): void
    {
        $this->activityLogger->record(
            $likeToggled->liked ? ActivityLogLikeEvent::Added : ActivityLogLikeEvent::Removed,
            $likeToggled->likeable,
            $likeToggled->user,
        );
    }
}
