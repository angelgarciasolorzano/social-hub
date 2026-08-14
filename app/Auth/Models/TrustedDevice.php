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
use Illuminate\Support\Facades\Log;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $name
 * @property string $user_agent
 * @property string $browser
 * @property string $os_name
 * @property string $os_version
 * @property bool $is_mobile
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
    'os_name',
    'os_version',
    'is_mobile',
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
            'is_mobile' => 'boolean',
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
     * Find the active trusted device for the same fingerprint, or null when
     * `$ip` is null. When `$lockForUpdate` is true, the read is serialised so
     * concurrent `store()` calls cannot both pass the check.
     */
    public static function findActiveMatch(
        User $user,
        string $userAgent,
        string $osName,
        ?string $ip,
        bool $lockForUpdate = false,
    ): ?self {
        if ($ip === null) {
            return null;
        }

        $builder = $user->trustedDevices()
            ->where('user_agent', $userAgent)
            ->where('os_name', $osName)
            ->where('ip', $ip)
            ->where('expires_at', '>', CarbonImmutable::now());

        if ($lockForUpdate) {
            $builder->lockForUpdate();
        }

        return $builder->first();
    }

    /**
     * Delete prior trusted devices for the same user + UA + OS + IP, excluding
     * `excludeId`. No-ops (with a warning) when `$ip` is null to avoid wiping
     * unrelated devices that share a UA string.
     */
    public static function pruneOlder(
        User $user,
        string $userAgent,
        string $osName,
        ?string $ip,
        int $excludeId,
    ): void {
        if ($ip === null) {
            Log::warning('TrustedDevice::pruneOlder called with null IP; skipping to avoid over-deletion.');

            return;
        }

        $user->trustedDevices()
            ->where('user_agent', $userAgent)
            ->where('os_name', $osName)
            ->where('ip', $ip)
            ->where('id', '!=', $excludeId)
            ->delete();
    }

    /**
     * Match the request's trusted_device cookie against an active device for
     * the user. Refreshes `last_used_at` on hit. Returns true to skip 2FA.
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
