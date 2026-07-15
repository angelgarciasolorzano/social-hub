<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Auth\Database\Factories\TrustedDeviceFactory;
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
 * @property int $user_id
 * @property string $token_hash
 * @property string $name
 * @property string $user_agent
 * @property string $browser
 * @property string $ip
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 *
 * @mixin IdeHelperTrustedDevice
 */
#[UseFactory(TrustedDeviceFactory::class)]
#[Fillable([
    'user_id',
    'token_hash',
    'name',
    'user_agent',
    'browser',
    'ip',
    'last_used_at',
    'expires_at',
])]
class TrustedDevice extends Model
{
    /**
     * @use HasFactory<TrustedDeviceFactory>
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
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the user that owns this trusted device.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether this device is still within its trust window.
     */
    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Validate whether the request's "trusted_device" cookie matches an active
     * trusted device for the given user. If a match is found, refresh its
     * `last_used_at` timestamp.
     *
     * Returns true when the user should bypass the two-factor challenge.
     */
    public static function validateAndTouch(User $user, Request $request): bool
    {
        $token = $request->cookie('trusted_device');

        if (! is_string($token) || $token === '') {
            return false;
        }

        $builder = $user->trustedDevices()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', CarbonImmutable::now());

        $device = $builder->first();

        if ($device === null) {
            return false;
        }

        $device->forceFill([
            'last_used_at' => CarbonImmutable::now(),
        ])->save();

        return true;
    }
}
