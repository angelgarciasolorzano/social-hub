<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

enum NotificationType: string
{
    case CommentOnPost = 'comment_on_post';

    case CommentReply = 'comment_reply';

    case LikesGrouped = 'likes_grouped';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $notificationType): string => $notificationType->value,
            self::cases(),
        );
    }
}
