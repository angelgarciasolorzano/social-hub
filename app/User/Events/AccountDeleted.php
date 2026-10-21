<?php

declare(strict_types=1);

namespace App\User\Events;

use App\User\Models\User;

/**
 * Dispatched after a user deletes their own account, with the in-memory instance
 * so its id stays available after the row is gone.
 */
final readonly class AccountDeleted
{
    public function __construct(public User $user) {}
}
