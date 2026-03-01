<?php

declare(strict_types=1);

namespace App\User\Models;

use App\Comment\Models\Comment;
use App\Like\Models\Like;
use App\Post\Models\Post;
use App\User\Enums\UserImageType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @mixin IdeHelperUser
 */
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

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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
     * Summary of posts
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Summary of comments
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Summary of likes
     *
     * @return HasMany<Like, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }
}
