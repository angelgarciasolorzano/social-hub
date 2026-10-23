<?php

declare(strict_types=1);

namespace App\User\Models;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Database\Factories\UserFactory;
use App\User\Enums\AccountStatus;
use App\User\Enums\PreferredLocale;
use App\User\Enums\UserImageType;
use App\User\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property CarbonImmutable|null $created_at
 * @property string|null $phone
 * @property PreferredLocale $preferred_locale
 * @property AccountStatus $status
 * @property string|null $biography
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property CarbonImmutable|null $recovery_codes_regenerated_at
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 *
 * @mixin IdeHelperUser
 */
#[UseFactory(UserFactory::class)]
#[UsePolicy(UserPolicy::class)]
#[Fillable([
    'name',
    'email',
    'phone',
    'preferred_locale',
    'biography',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'app_authentication_secret',
    'app_authentication_recovery_codes',
])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasMedia
{
    /**
     * @use HasFactory<UserFactory>
     */
    use HasFactory;

    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use InteractsWithMedia;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected string $guard_name = 'admin';

    /**
     * The morph name used for polymorphic relations.
     */
    public const string MORPH_NAME = 'user';

    /**
     * The default profile picture path used as fallback.
     */
    public const string DEFAULT_PROFILE_PICTURE_PATH = '/default-profile-picture.png';

    /**
     * The default cover image path used as fallback.
     */
    public const string DEFAULT_COVER_IMAGE_PATH = '/default-cover.svg';

    /**
     * Glob pattern for all test profile images.
     */
    public const string TEST_PROFILE_IMAGES_GLOB_PATH = 'User/Seeders/Images/profile/*.{jpg,jpeg,png}';

    /**
     * Glob pattern for all test cover images.
     */
    public const string TEST_COVER_IMAGES_GLOB_PATH = 'User/Seeders/Images/cover/*.{jpg,jpeg,png}';

    /**
     * Glob pattern for the test user's profile image.
     */
    public const string TEST_USER_IMAGE_GLOB_PATH = 'User/Seeders/Images/profile/user-test.{jpg,jpeg,png}';

    /**
     * Glob pattern for the test user's cover image.
     */
    public const string TEST_COVER_IMAGE_GLOB_PATH = 'User/Seeders/Images/cover/user-cover-test.{jpg,jpeg,png}';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'immutable_datetime',
            'preferred_locale' => PreferredLocale::class,
            'status' => AccountStatus::class,
            'two_factor_confirmed_at' => 'datetime',
            'recovery_codes_regenerated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Whether an administrator suspended the account, which blocks every sign-in path.
     */
    public function isSuspended(): bool
    {
        return $this->status === AccountStatus::Suspended;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->hasVerifiedEmail()
            && ! $this->isSuspended()
            && $this->can(AdminPermission::AdminAccess->value);
    }

    /**
     * Register user media collections for profile and cover images.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(UserImageType::PROFILE_PICTURE->value())
            ->useFallbackUrl(self::DEFAULT_PROFILE_PICTURE_PATH)
            ->useFallbackPath(public_path(self::DEFAULT_PROFILE_PICTURE_PATH))
            ->singleFile();

        $this->addMediaCollection(UserImageType::COVER_IMAGE->value())
            ->useFallbackUrl(self::DEFAULT_COVER_IMAGE_PATH)
            ->useFallbackPath(public_path(self::DEFAULT_COVER_IMAGE_PATH))
            ->singleFile();
    }

    /**
     * Replace the given recovery code WITHOUT generating a new one
     */
    public function replaceRecoveryCode(string $code): void
    {
        /** @var array<int, string> $recoveryCodes */
        $recoveryCodes = $this->recoveryCodes();

        /** @var array<int, string> $updatedCodes */
        $updatedCodes = array_values(
            array_filter(
                $recoveryCodes,
                fn (string $recoveryCode): bool => $recoveryCode !== $code
            )
        );

        $this->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(
                json_encode($updatedCodes)
            ),
        ])->save();

        event(new RecoveryCodeReplaced($this, $code));
    }

    /**
     * Get all posts created by the user.
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get all comments made by the user.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get all likes given by the user.
     *
     * @return HasMany<Like, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Get all trusted devices for this user.
     *
     * @return HasMany<TrustedDevice, $this>
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * Get all trusted device events recorded against this user.
     *
     * @return HasMany<TrustedDeviceEvent, $this>
     */
    public function trustedDeviceEvents(): HasMany
    {
        return $this->hasMany(TrustedDeviceEvent::class);
    }

    /**
     * Get the suspensions and reactivations of the account, most recent first.
     *
     * @return HasMany<AccountStatusChange, $this>
     */
    public function accountStatusChanges(): HasMany
    {
        return $this->hasMany(AccountStatusChange::class)->latest()->latest('id');
    }

    /**
     * Get the activity log records of the actions this user performed.
     *
     * @return MorphMany<ActivityLog, $this>
     */
    public function causedActivityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'causer');
    }
}
