<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Enums;

enum FriendshipState: string
{
    case None = 'none';

    case RequestSent = 'request_sent';

    case RequestReceived = 'request_received';

    case Friends = 'friends';

    /**
     * Resolve the state from the pair's row, or its absence, for the given viewer.
     * Takes the stored columns, so a query that only selects them needs no model.
     */
    public static function forViewer(?FriendshipStatus $friendshipStatus, ?int $requesterId, int $viewerId): self
    {
        return match ($friendshipStatus) {
            null => self::None,
            FriendshipStatus::Accepted => self::Friends,
            FriendshipStatus::Pending => $requesterId === $viewerId ? self::RequestSent : self::RequestReceived,
        };
    }
}
