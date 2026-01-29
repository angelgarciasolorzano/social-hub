<?php

namespace App\User\Models;

use App\Comment\Models\Comment;
use App\Like\Models\Like;
use App\Post\Models\Post;
use App\User\Enums\UserImageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia {
    use HasFactory, TwoFactorAuthenticatable, InteractsWithMedia;

    public const MORPH_NAME = 'user';

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
            ->useFallbackUrl('/default-profile-picture.png')
            ->useFallbackPath(public_path('/default-profile-picture.png'))
            ->singleFile();
        
        $this->addMediaCollection(UserImageType::COVER_IMAGE->value())
            ->useFallbackUrl('/default-cover.svg')
            ->useFallbackPath(public_path('/default-cover.svg'))
            ->singleFile();
    }

    public function posts(): HasMany {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany {
        return $this->hasMany(Like::class);
    }
}