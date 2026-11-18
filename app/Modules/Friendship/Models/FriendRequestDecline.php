<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Models;

use App\Modules\Friendship\Database\Factories\FriendRequestDeclineFactory;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * The last time a user declined another user's friend request: while the cooldown lasts,
 * the requester cannot ask the same person again.
 *
 * @property int $id
 * @property int $requester_id
 * @property int $receiver_id
 * @property CarbonImmutable $declined_at
 * @property-read User $requester
 * @property-read User $receiver
 *
 * @mixin IdeHelperFriendRequestDecline
 */
#[UseFactory(FriendRequestDeclineFactory::class)]
#[Fillable([
    'requester_id',
    'receiver_id',
    'declined_at',
])]
#[WithoutTimestamps]
class FriendRequestDecline extends Model
{
    /** @use HasFactory<FriendRequestDeclineFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'requester_id' => 'integer',
            'receiver_id' => 'integer',
            'declined_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the user whose request was declined.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the user who declined the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Limit the query to a decline of the requester by the receiver still within the cooldown.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function activeFor(Builder $builder, User $requester, User $receiver, int $cooldownDays): void
    {
        $builder
            ->where('requester_id', $requester->id)
            ->where('receiver_id', $receiver->id)
            ->where('declined_at', '>', now()->subDays($cooldownDays));
    }
}
