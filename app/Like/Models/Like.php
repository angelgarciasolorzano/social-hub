<?php

namespace App\Like\Models;

use App\User\Models\User;
use Database\Factories\LikeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperLike
 */
class Like extends Model
{
    /** @use HasFactory<LikeFactory> */
    use HasFactory;

    public const MORPH_NAME = 'like';

    public const MORPH_COLUMN = 'likeable';

    /**
     * Get the user that owns the like.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent model that the like belongs to (e.g., Post).
     *
     * @return MorphTo<Model, $this>
     */
    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }
}
