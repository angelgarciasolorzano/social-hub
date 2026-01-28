<?php

namespace App\Comment\Models;

use App\Models\Like;
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

    public const MORPH_NAME = 'comment';

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

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, self::MORPH_COLUMN)->with('user');
    }

    /**
     * Get all of the comment's likes.
     *
     * @return MorphMany
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
