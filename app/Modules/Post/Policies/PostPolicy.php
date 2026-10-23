<?php

declare(strict_types=1);

namespace App\Modules\Post\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Post\Models\Post;
use App\User\Models\User;

/**
 * Posts are read-only in the Admin panel: administrators with the posts permissions can
 * list and open them, and nobody can create, change or delete them from there.
 */
final class PostPolicy
{
    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->can(AdminPermission::AdminPostsViewAny->value);
    }

    /**
     * Without a post it answers whether posts can be opened at all (static widget checks);
     * a given post must also still have its author.
     */
    public function view(User $authenticatedUser, ?Post $post = null): bool
    {
        if ($post instanceof Post && ! $post->user instanceof User) {
            return false;
        }

        return $authenticatedUser->can(AdminPermission::AdminPostsView->value);
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }

    public function restore(): bool
    {
        return false;
    }

    public function restoreAny(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }
}
