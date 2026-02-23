<?php

namespace App\Like\Models;

use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Like extends Model
{
    /** @use HasFactory<\Database\Factories\LikeFactory> */
    use HasFactory;

    public const MORPH_NAME = 'like';

    public const MORPH_COLUMN = 'likeable';

    /**
     * Get the user that owns the like.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent model that the like belongs to (e.g., Post).
     */
    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }
}
