<?php

namespace App\Friendship\Models;

use App\Friendship\Enums\FriendshipStatus;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFriendship
 */
class Friendship extends Model
{
    /** @use HasFactory<\Database\Factories\FriendshipFactory> */
    use HasFactory;

    public const REQUESTER_ID = 'requester_id';

    public const RECEIVER_ID = 'receiver_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        self::REQUESTER_ID,
        self::RECEIVER_ID,
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::REQUESTER_ID => 'integer',
            self::RECEIVER_ID => 'integer',
            'status' => FriendshipStatus::class,
        ];
    }

    /**
     * Get the user that sent the friend request.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, self::REQUESTER_ID);
    }

    /**
     * Get the user that received the friend request.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, self::RECEIVER_ID);
    }
}
