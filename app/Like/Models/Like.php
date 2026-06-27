<?php

declare(strict_types=1);

namespace App\Like\Models;

use App\User\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\LikeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property CarbonImmutable|null $created_at
 *
 * @mixin IdeHelperLike
 */
class Like extends Model
{
    /** @use HasFactory<LikeFactory> */
    use HasFactory;

    /**
     * The morph name used for polymorphic relations.
     */
    public const string MORPH_NAME = 'like';

    /**
     * The morph column name for the likeable relation.
     */
    public const string MORPH_COLUMN = 'likeable';

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
