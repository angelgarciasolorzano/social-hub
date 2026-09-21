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
 * @property int|null $user_id
 * @property TrustedDeviceAction $action
 * @property string|null $device_label
 * @property bool|null $device_is_mobile
 * @property string|null $device_os_name
 * @property string|null $ip
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 *
 * @mixin IdeHelperTrustedDeviceEvent
 */
#[UseFactory(TrustedDeviceEventFactory::class)]
#[Fillable([
    'user_id',
    'action',
    'device_label',
    'device_is_mobile',
    'device_os_name',
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
            'device_is_mobile' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Record a trusted device event. Centralised so every hook
     * (controller actions, listeners) writes through a single factory.
     */
    public static function record(
        TrustedDevice $trustedDevice,
        User $user,
        TrustedDeviceAction $trustedDeviceAction,
        Request $request,
    ): self {
        return self::query()
            ->create([
                'user_id' => $user->getKey(),
                'action' => $trustedDeviceAction,
                'device_label' => $trustedDevice->name,
                'device_is_mobile' => $trustedDevice->is_mobile,
                'device_os_name' => $trustedDevice->os_name,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
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
