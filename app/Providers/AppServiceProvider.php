<?php

declare(strict_types=1);

namespace App\Providers;

use App\Comment\Models\Comment;
use App\Like\Models\Like;
use App\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
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
            Like::MORPH_NAME => Like::class,
            Comment::MORPH_NAME => Comment::class,
        ]);
    }
}
