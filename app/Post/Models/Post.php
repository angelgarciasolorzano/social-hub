<?php

namespace App\Post\Models;

use App\Models\Comment;
use App\Models\Like;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @mixin IdeHelperPost
 * @property CarbonImmutable|null $created_at
 */
class Post extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const PATH = 'posts';
    
    protected $fillable = [
        'content',
        'user_id',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('posts_images')
            ->useFallbackUrl(asset('post-placeholder.png'))
            ->useFallbackPath(public_path('post-placeholder.png'))
            ->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}