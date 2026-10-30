<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Enums;

enum AnnouncementReviewAction: string
{
    case Submitted = 'submitted';

    case Approved = 'approved';

    case Rejected = 'rejected';

    case Resubmitted = 'resubmitted';
}
