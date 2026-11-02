<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use Illuminate\Database\Eloquent\Relations\Relation;

trait ConstrainsAnnouncementSubmissionReview
{
    /**
     * Load the last submission once for each table row when deciding which actions to show.
     *
     * @param  Relation<*, *, *>  $reviewQuery
     */
    protected static function constrainLatestSubmissionReview(Relation $reviewQuery): void
    {
        $reviewQuery
            ->whereIn('action', [
                AnnouncementReviewAction::Submitted->value,
                AnnouncementReviewAction::Resubmitted->value,
            ])
            ->reorder()
            ->latest()
            ->orderByDesc('id')
            ->limit(1);
    }
}
