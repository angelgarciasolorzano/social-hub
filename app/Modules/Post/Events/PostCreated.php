<?php

declare(strict_types=1);

namespace App\Modules\Post\Events;

use App\Modules\Post\Models\Post;

/**
 * Dispatched after a user publishes a post, before its optional image is attached.
 */
final readonly class PostCreated
{
    public function __construct(public Post $post) {}
}
