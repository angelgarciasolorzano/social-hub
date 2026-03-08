<?php

declare(strict_types=1);

namespace App\User\Models;

use App\Comment\Models\Comment;
use App\Like\Models\Like;
use App\Post\Models\Post;
use App\User\Enums\UserImageType;
use App\User\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @mixin IdeHelperUser
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements HasMedia
{
    /**
     * @use HasFactory<UserFactory>
     */
    use HasFactory;

    use InteractsWithMedia, TwoFactorAuthenticatable;

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
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
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
