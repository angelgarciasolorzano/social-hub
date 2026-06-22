<?php

declare(strict_types=1);

namespace App\User\Models;

use App\Comment\Models\Comment;
use App\Like\Models\Like;
use App\Post\Models\Post;
use App\User\Enums\UserImageType;
use App\User\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @mixin IdeHelperUser
 */
#[UseFactory(UserFactory::class)]
#[Fillable([
    'name',
    'email',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable implements HasMedia
{
    /**
     * @use HasFactory<UserFactory>
     */
    use HasFactory;

    use InteractsWithMedia;
    use Notifiable;
    use TwoFactorAuthenticatable;

    public const MORPH_NAME = 'user';

    public const DEFAULT_PROFILE_PICTURE_PATH = '/default-profile-picture.png';

    public const DEFAULT_COVER_IMAGE_PATH = '/default-cover.svg';

    /**
     * Glob pattern for all test profile images.
     */
    public const TEST_PROFILE_IMAGES_GLOB_PATH = 'User/Seeders/Images/profile/*.{jpg,jpeg,png}';

    /**
     * Glob pattern for all test cover images.
     */
    public const TEST_COVER_IMAGES_GLOB_PATH = 'User/Seeders/Images/cover/*.{jpg,jpeg,png}';

    /**
     * Glob pattern for the test user's profile image.
     */
    public const TEST_USER_IMAGE_GLOB_PATH = 'User/Seeders/Images/profile/user-test.{jpg,jpeg,png}';

    /**
     * Glob pattern for the test user's cover image.
     */
    public const TEST_COVER_IMAGE_GLOB_PATH = 'User/Seeders/Images/cover/user-cover-test.{jpg,jpeg,png}';

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
            'password' => 'hashed',
        ];
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
        $recoveryCodes = $this->recoveryCodes();

        $updatedCodes = array_values(array_filter($recoveryCodes, fn ($recoveryCode): bool => $recoveryCode !== $code));

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
}
