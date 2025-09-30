<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $commentable_id
 * @property int $commentable_type
 * @property string $content
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 *
 * @property-read User $user
 */
class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory;

    /** @var string[]  */
    protected $fillable = [
        'user_id',
        'commentable_id',
        'commentable_type',
        'content'
    ];

    /**
     * Get the user that wrote the comment.
     * Obtener el usuario que escribió el comentario
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent model that the comment belongs to (e.g., Post).
     * Obtener el modelo padre al que pertenece el comentario (p.ej., Post)
     *
     * @return MorphTo
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->with('user');
    }

    /**
     * Get all of the comment's likes.
     * Obtener todos los likes del comentario
     *
     * @return MorphMany
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
