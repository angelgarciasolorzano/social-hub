<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Auth\Database\Factories\TrustedDeviceEventFactory;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Override;

/**
 * @property int $id
 * @property int|null $trusted_device_id
 * @property int|null $user_id
 * @property TrustedDeviceAction $action
 * @property string|null $ip
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 *
 * @mixin IdeHelperTrustedDeviceEvent
 */
#[UseFactory(TrustedDeviceEventFactory::class)]
#[Fillable([
    'trusted_device_id',
    'user_id',
    'action',
    'ip',
    'user_agent',
])]
class TrustedDeviceEvent extends Model
{
    /**
     * @use HasFactory<TrustedDeviceEventFactory>
     */
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
            'action' => TrustedDeviceAction::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Record a trusted device event. Centralised so every hook
     * (controller actions, listeners) writes through a single factory.
     *
     * @param  TrustedDevice|null  $trustedDevice  null for global events (e.g. 2FA disabled).
     */
    public static function record(
        ?TrustedDevice $trustedDevice,
        User $user,
        TrustedDeviceAction $trustedDeviceAction,
        Request $request,
    ): self {
        return self::query()
            ->create([
                'trusted_device_id' => $trustedDevice?->getKey(),
                'user_id' => $user->getKey(),
                'action' => $trustedDeviceAction,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
    }

    /**
     * Get the trusted device this event refers to, when applicable.
     *
     * @return BelongsTo<TrustedDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(TrustedDevice::class, 'trusted_device_id');
    }

    /**
     * Get the user this event refers to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
