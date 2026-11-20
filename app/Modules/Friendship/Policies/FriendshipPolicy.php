<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Policies;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Friendship\Models\Friendship;
use App\User\Models\User;

final class FriendshipPolicy
{
    /**
     * List the friendships of a user in the Admin panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(AdminPermission::AdminUsersFriendships->value);
    }

    /**
     * Send a request to the receiver: never to oneself, and never from or to a suspended account.
     */
    public function create(User $user, User $receiver): bool
    {
        return $user->isNot($receiver)
            && ! $user->isSuspended()
            && ! $receiver->isSuspended();
    }

    /**
     * Either of the two users of the row, or an administrator who reads friendships in the panel.
     */
    public function view(User $user, Friendship $friendship): bool
    {
        return $friendship->involves($user)
            || $user->can(AdminPermission::AdminUsersFriendships->value);
    }

    public function accept(User $user, Friendship $friendship): bool
    {
        return $this->isPendingRequestReceivedBy($user, $friendship);
    }

    public function decline(User $user, Friendship $friendship): bool
    {
        return $this->isPendingRequestReceivedBy($user, $friendship);
    }

    public function cancel(User $user, Friendship $friendship): bool
    {
        return $friendship->isPending() && $friendship->requester_id === $user->id;
    }

    /**
     * Remove an accepted friendship, whoever of both sent the request.
     */
    public function delete(User $user, Friendship $friendship): bool
    {
        return $friendship->isAccepted() && $friendship->involves($user);
    }

    public function update(): bool
    {
        return false;
    }

    public function restore(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }

    public function restoreAny(): bool
    {
        return false;
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }

    /**
     * Only the receiver answers a request, and only while it waits for an answer.
     */
    private function isPendingRequestReceivedBy(User $user, Friendship $friendship): bool
    {
        return $friendship->isPending() && $friendship->receiver_id === $user->id;
    }
}
