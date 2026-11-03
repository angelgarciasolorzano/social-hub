<?php

declare(strict_types=1);

namespace App\Modules\Notification\Listeners;

use App\Modules\Like\Events\LikeToggled;
use App\Modules\Notification\Services\LikeNotificationGroupService;
use App\User\Models\User;

final readonly class LikeNotificationSubscriber
{
    public function __construct(private LikeNotificationGroupService $likeNotificationGroupService) {}

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
        $recipient = $likeToggled->likeable->user;

        if (! $recipient instanceof User || $recipient->is($likeToggled->user)) {
            return;
        }

        $this->likeNotificationGroupService->sync(
            $recipient,
            $likeToggled->user,
            $likeToggled->likeable,
            $likeToggled->liked,
        );
    }
}
