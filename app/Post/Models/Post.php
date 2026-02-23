<?php

namespace App\Post\Models;

use App\Comment\Models\Comment;
use App\Like\Models\Like;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @mixin IdeHelperPost
 *
 * @property CarbonImmutable|null $created_at
 */
class Post extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const MORPH_NAME = 'post';

    public const PATH = 'posts';

    public const POSTS_IMAGES_MEDIA_COLLECTION = 'posts_images';

    protected $fillable = [
        'content',
        'user_id',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::POSTS_IMAGES_MEDIA_COLLECTION)->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, Comment::MORPH_COLUMN);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, Like::MORPH_COLUMN);
    }
}
