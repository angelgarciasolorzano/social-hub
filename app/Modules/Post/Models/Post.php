<?php

declare(strict_types=1);

namespace App\Modules\Post\Models;

use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Database\Factories\PostFactory;
use App\Modules\Post\Policies\PostPolicy;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $content
 * @property int $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, Like> $likes
 * @property-read int|null $root_comments_count
 * @property-read int|null $replies_count
 * @property-read int|null $likes_count
 * @property-read bool|null $liked_by_me
 *
 * @mixin IdeHelperPost
 */
#[UseFactory(PostFactory::class)]
#[UsePolicy(PostPolicy::class)]
#[Fillable([
    'content',
    'user_id',
])]
class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * The morph name used for polymorphic relations.
     */
    public const string MORPH_NAME = 'post';

    /**
     * The base path for posts.
     */
    public const string PATH = 'posts';

    /**
     * The media collection name for post images.
     */
    public const string POSTS_IMAGES_MEDIA_COLLECTION = 'posts_images';

    /**
     * Glob pattern for test images used in seeding.
     */
    public const string TEST_IMAGES_GLOB_PATH = 'Modules/Post/Database/Seeders/Images/*.{jpg,jpeg,png}';

    /**
     * Register the media collections for the post images.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::POSTS_IMAGES_MEDIA_COLLECTION)->singleFile();
    }

    /**
     * Get the user who created the post.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all comments for this post.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, Comment::MORPH_COLUMN);
    }

    /**
     * Get all likes for this post.
     *
     * @return MorphMany<Like, $this>
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, Like::MORPH_COLUMN);
    }

    /**
     * Preload, in the same query, the likes, whether the viewer liked the post and the total of comments.
     *
     * The comment total is the live root comments plus every reply, including the replies under a deleted comment.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withEngagementFor(Builder $query, User $user): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $liveRootComments = Comment::query()
            ->toBase()
            ->selectRaw('count(*)')
            ->whereColumn('comments.commentable_id', 'posts.id')
            ->where('comments.commentable_type', self::MORPH_NAME);

        $replies = Comment::query()
            ->toBase()
            ->selectRaw('count(*)')
            ->where('comments.commentable_type', Comment::MORPH_NAME)
            ->whereIn('comments.commentable_id', Comment::withTrashed()
                ->select('id')
                ->where('commentable_type', self::MORPH_NAME)
                ->whereColumn('commentable_id', 'posts.id'));

        $query
            ->selectSub($liveRootComments, 'root_comments_count')
            ->selectSub($replies, 'replies_count')
            ->withCount('likes')
            ->withExists(['likes as liked_by_me' => fn (Builder $builder): Builder => $builder->whereBelongsTo($user)]);
    }

    /**
     * Get the live root comments plus every reply, reusing the preloaded counts when present.
     */
    public function commentsTotal(): int
    {
        $loadedRootComments = $this->getAttributes()['root_comments_count'] ?? null;
        $loadedReplies = $this->getAttributes()['replies_count'] ?? null;

        if (is_numeric($loadedRootComments) && is_numeric($loadedReplies)) {
            return (int) $loadedRootComments + (int) $loadedReplies;
        }

        $repliesCount = Comment::query()
            ->where('commentable_type', Comment::MORPH_NAME)
            ->whereIn('commentable_id', $this->comments()->withTrashed()->select('comments.id'))
            ->count();

        return $this->comments()->count() + $repliesCount;
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
     * Check if the given user liked this post, reusing a preloaded `liked_by_me` of that same user.
     */
    public function isLikedBy(User $user): bool
    {
        $loadedExists = $this->getAttributes()['liked_by_me'] ?? null;

        return $loadedExists === null
            ? $this->likes()->whereBelongsTo($user)->exists()
            : (bool) $loadedExists;
    }
}
