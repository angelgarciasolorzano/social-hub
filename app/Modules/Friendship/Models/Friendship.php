<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Models;

use App\Modules\Friendship\Database\Factories\FriendshipFactory;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Policies\FriendshipPolicy;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Override;

/**
 * @property int $id
 * @property int $requester_id
 * @property int $receiver_id
 * @property FriendshipStatus $status
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int $user_low_id
 * @property-read int $user_high_id
 * @property-read User $requester
 * @property-read User $receiver
 *
 * @mixin IdeHelperFriendship
 */
#[UseFactory(FriendshipFactory::class)]
#[UsePolicy(FriendshipPolicy::class)]
#[Fillable([
    'requester_id',
    'receiver_id',
    'status',
    'accepted_at',
])]
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
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
            'user_low_id' => 'integer',
            'user_high_id' => 'integer',
            'status' => FriendshipStatus::class,
            'accepted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the user who sent the friend request.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the user who received the friend request.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Whether the request is still waiting for an answer.
     */
    public function isPending(): bool
    {
        return $this->status === FriendshipStatus::Pending;
    }

    /**
     * Whether both users are friends.
     */
    public function isAccepted(): bool
    {
        return $this->status === FriendshipStatus::Accepted;
    }

    /**
     * Whether the user is one of the two people of the row.
     */
    public function involves(User $user): bool
    {
        return $this->requester_id === $user->id || $this->receiver_id === $user->id;
    }

    /**
     * Get the id of the other person of the row, seen from the given user.
     *
     * @throws InvalidArgumentException when the user is not part of the row
     */
    public function otherUserId(User $user): int
    {
        throw_unless($this->involves($user), InvalidArgumentException::class, 'The user is not part of this friendship.');

        return $this->requester_id === $user->id ? $this->receiver_id : $this->requester_id;
    }

    /**
     * Limit the query to the rows where the user is the requester or the receiver.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function involving(Builder $builder, User $user): void
    {
        $builder->where(fn (Builder $builder): Builder => $builder
            ->where('requester_id', $user->id)
            ->orWhere('receiver_id', $user->id));
    }

    /**
     * Limit the query to the row of the pair, whichever of both sent the request.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function between(Builder $builder, User $firstUser, User $secondUser): void
    {
        $builder
            ->where('user_low_id', min($firstUser->id, $secondUser->id))
            ->where('user_high_id', max($firstUser->id, $secondUser->id));
    }

    /**
     * Limit the query to the requests waiting for an answer.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function pending(Builder $builder): void
    {
        $builder->where('status', FriendshipStatus::Pending);
    }

    /**
     * Limit the query to the accepted friendships.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function accepted(Builder $builder): void
    {
        $builder->where('status', FriendshipStatus::Accepted);
    }
}
