<?php

declare(strict_types=1);

namespace App\Modules\Comment\Models;

use App\Modules\Comment\Database\Factories\CommentFactory;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Like\Models\Like;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @property int $id
 * @property string $content
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable|null $deleted_at
 * @property int|null $replied_to_user_id
 * @property-read User $user
 * @property-read User|null $repliedToUser
 *
 * @mixin IdeHelperComment
 */
#[UseFactory(CommentFactory::class)]
#[Fillable([
    'user_id',
    'replied_to_user_id',
    'commentable_id',
    'commentable_type',
    'content',
    'edited_at',
])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The morph name used for polymorphic relations.
     */
    public const string MORPH_NAME = 'comment';

    /**
     * The morph column name for child comments.
     */
    public const string MORPH_COLUMN = 'commentable';

    #[Override]
    protected function casts(): array
    {
        return [
            'commentable_type' => CommentType::class,
            'edited_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the user that wrote the comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user mentioned when this comment replies to another reply.
     *
     * @return BelongsTo<User, $this>
     */
    public function repliedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_to_user_id');
    }

    /**
     * Get the parent model that the comment belongs to (e.g., Post).
     *
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all replies (child comments) for this comment.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, self::MORPH_COLUMN)->with('user');
    }

    /**
     * Check if this comment has replies.
     */
    public function hasReplies(): bool
    {
        return $this->repliesCount() > 0;
    }

    /**
     * Get the total number of replies, reusing a preloaded `comments_count` when present.
     */
    public function repliesCount(): int
    {
        $loadedCount = $this->getAttributes()['comments_count'] ?? null;

        return is_numeric($loadedCount) ? (int) $loadedCount : $this->comments()->count();
    }

    /**
     * Get the total number of likes, reusing a preloaded `likes_count` when present.
     */
    public function likesCount(): int
    {
        $loadedCount = $this->getAttributes()['likes_count'] ?? null;

        return is_numeric($loadedCount) ? (int) $loadedCount : $this->likes()->count();
    }

    /**
     * Check if the given user liked this comment, reusing a preloaded `liked_by_me` of that same user.
     */
    public function isLikedBy(User $user): bool
    {
        $loadedExists = $this->getAttributes()['liked_by_me'] ?? null;

        return $loadedExists === null
            ? $this->likes()->whereBelongsTo($user)->exists()
            : (bool) $loadedExists;
    }

    /**
     * Get all of the comment's likes.
     *
     * @return MorphMany<Like, $this>
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, Like::MORPH_COLUMN);
    }
}
