<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Enums\Events;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;

enum ActivityLogContentEvent: string implements ActivityLogEvent
{
    case PostCreated = 'content.post_created';

    case CommentCreated = 'content.comment_created';

    case CommentReplied = 'content.comment_replied';

    case CommentUpdated = 'content.comment_updated';

    case CommentDeleted = 'content.comment_deleted';

    public function eventName(): string
    {
        return $this->value;
    }

    public function logName(): ActivityLogName
    {
        return ActivityLogName::Content;
    }
}
