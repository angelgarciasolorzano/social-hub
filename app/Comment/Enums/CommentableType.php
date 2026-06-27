<?php

declare(strict_types=1);

namespace App\Comment\Enums;

use App\Comment\Models\Comment;
use App\Post\Models\Post;

enum CommentableType: string
{
    case POST = Post::MORPH_NAME;
    case COMMENT = Comment::MORPH_NAME;

    public function modelClass(): string
    {
        return match ($this) {
            self::POST => Post::class,
            self::COMMENT => Comment::class,
        };
    }
}
