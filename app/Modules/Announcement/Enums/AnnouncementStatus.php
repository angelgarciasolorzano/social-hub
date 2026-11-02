<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Enums;

enum AnnouncementStatus: string
{
    case Draft = 'draft';

    case InReview = 'in_review';

    case Approved = 'approved';

    case Rejected = 'rejected';
}
