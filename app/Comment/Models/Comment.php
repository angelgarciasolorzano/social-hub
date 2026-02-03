<?php

namespace App\Comment\Models;

use App\Like\Models\Like;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory;

    /**
     * The morph name used for polymorphic relations.
     */
    public const MORPH_NAME = 'comment';

    /**
     * The morph column name for child comments.
     */
    public const MORPH_COLUMN = 'commentable';

    /** @var string[]  */
    protected $fillable = [
        'user_id',
        'commentable_id',
        'commentable_type',
        'content'
    ];

    /**
     * Get the user that wrote the comment.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent model that the comment belongs to (e.g., Post).
     *
     * @return MorphTo
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }


    /**
     * Get all replies (child comments) for this comment.
     *
     * @return MorphMany
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, self::MORPH_COLUMN)->with('user');
    }


    /**
     * Check if this comment has replies.
     * 
     * @return bool
     */
    public function hasReplies(): bool
    {
        return $this->comments()->exists();
    }


    /**
     * Get the total number of replies for this comment.
     * 
     * @return int
     */
    public function repliesCount(): int
    {
        return $this->comments()->count();
    }

    /**
     * Get all of the comment's likes.
     *
     * @return MorphMany
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, Like::MORPH_COLUMN);
    }
}
