<?php

declare(strict_types=1);

namespace App\Modules\Admin\Models;

use App\Modules\Admin\Database\Factories\AdminInvitationFactory;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property string $email
 * @property int $invited_by_user_id
 * @property int|null $target_user_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $inviter
 * @property-read User|null $targetUser
 */
#[UseFactory(AdminInvitationFactory::class)]
#[Fillable([
    'email',
    'invited_by_user_id',
    'target_user_id',
    'token_hash',
    'expires_at',
    'accepted_at',
])]
class AdminInvitation extends Model
{
    /** @use HasFactory<AdminInvitationFactory> */
    use HasFactory;

    /**
     * Normalize invitation email addresses before storing them.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (string $email): string => mb_strtolower(trim($email)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the user who sent this invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Get the existing user this invitation targets, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
