<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    /** @use HasFactory<\Database\Factories\FriendshipFactory> */
    use HasFactory;

    protected $fillable = [
        'requester_id',
        'receiver_id',
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
            'requester_id' => 'integer',
            'receiver_id' => 'integer',
            'status' => FriendshipStatus::class,
        ];
    }

    /**
     * Get the user that sent the friend request.
     * Obtener el usuario que envió la solicitud de amistad
     *
     * @return BelongsTo
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the user that received the friend request.
     * Obtener el usuario que recibió la solicitud de amistad
     *
     * @return BelongsTo
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
