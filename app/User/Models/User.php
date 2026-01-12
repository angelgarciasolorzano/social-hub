<?php

namespace App\User\Models;

use App\Models\Comment;
use App\Models\Like;
use App\Post\Models\Post;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia {
    use HasFactory, TwoFactorAuthenticatable, InteractsWithMedia;

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
        $this->addMediaCollection('profile_picture')->singleFile();
        $this->addMediaCollection('cover_image')->singleFile();
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