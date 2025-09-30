<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $content
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Post extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['content', 'image_file', 'user_id'];

    /**
     * Register the media collections.
     * Registrar las colecciones de medios
     *
     * @return void
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('posts_images')->singleFile();
    }

    /**
     * Get the user that owns the post.
     * Obtener el usuario que posee el post
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all of the post's comments.
     * Obtener todos los comentarios del post
     *
     * @return MorphMany
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get all of the post's likes.
     * Obtener todos los likes del post
     *
     * @return MorphMany
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
