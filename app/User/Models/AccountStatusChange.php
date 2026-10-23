<?php

declare(strict_types=1);

namespace App\User\Models;

use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Enums\AccountStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * An append-only record of a suspension or reactivation, with its mandatory reason.
 *
 * @property int $id
 * @property int $user_id
 * @property AccountStatus $status
 * @property string $reason
 * @property int|null $changed_by
 * @property CarbonImmutable|null $created_at
 *
 * @mixin IdeHelperAccountStatusChange
 */
#[UseFactory(AccountStatusChangeFactory::class)]
#[Fillable([
    'user_id',
    'status',
    'reason',
    'changed_by',
])]
class AccountStatusChange extends Model
{
    /**
     * @use HasFactory<AccountStatusChangeFactory>
     */
    use HasFactory;

    /**
     * The morph name used for polymorphic relations.
     */
    public const string MORPH_NAME = 'account_status_change';

    /**
     * The maximum length of the reason, shared by the service and the admin form.
     */
    public const int REASON_MAX_LENGTH = 1000;

    /**
     * The table only records when a change happened, it is never updated.
     */
    public const null UPDATED_AT = null;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the account whose status changed.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the administrator who made the change, null once that account is deleted.
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
