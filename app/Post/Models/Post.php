<?php

declare(strict_types=1);

namespace App\Post\Models;

use App\Comment\Models\Comment;
use App\Like\Models\Like;
use App\Post\Factories\PostFactory;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property CarbonImmutable|null $created_at
 *
 * @mixin IdeHelperPost
 */
#[UseFactory(PostFactory::class)]
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
    public const MORPH_NAME = 'post';

    /**
     * The base path for posts.
     */
    public const PATH = 'posts';

    /**
     * The media collection name for post images.
     */
    public const POSTS_IMAGES_MEDIA_COLLECTION = 'posts_images';

    /**
     * Glob pattern for test images used in seeding.
     */
    public const TEST_IMAGES_GLOB_PATH = 'Post/Seeders/Images/*.{jpg,jpeg,png}';

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
}
