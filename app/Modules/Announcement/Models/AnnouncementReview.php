<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Models;

use App\Modules\Announcement\Database\Factories\AnnouncementReviewFactory;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * An append-only record of an announcement submission or review decision.
 *
 * @property int $id
 * @property int $announcement_id
 * @property AnnouncementReviewAction $action
 * @property string|null $comment
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $created_at
 * @property-read Announcement $announcement
 * @property-read User|null $reviewedBy
 *
 * @mixin IdeHelperAnnouncementReview
 */
#[UseFactory(AnnouncementReviewFactory::class)]
#[Fillable([
    'announcement_id',
    'action',
    'comment',
    'reviewed_by',
])]
class AnnouncementReview extends Model
{
    /** @use HasFactory<AnnouncementReviewFactory> */
    use HasFactory;

    /**
     * Review history has a creation time but is never updated.
     */
    public const null UPDATED_AT = null;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'action' => AnnouncementReviewAction::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the announcement that owns this review entry.
     *
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * Get the user who performed the action, null once that account is deleted.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
