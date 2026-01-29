<?php

namespace App\Providers;

use App\Comment\Models\Comment;
use App\Models\Like;
use App\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            User::MORPH_NAME => User::class,
            Post::MORPH_NAME => Post::class,
            'like' => Like::class,
            Comment::MORPH_NAME => Comment::class,
        ]);
    }
}
