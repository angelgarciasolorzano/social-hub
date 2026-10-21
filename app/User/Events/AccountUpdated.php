<?php

declare(strict_types=1);

namespace App\User\Events;

use App\User\Models\User;

/**
 * Dispatched after a user changes their account data. It carries only the names
 * of the changed attributes, never their old or new values.
 */
final readonly class AccountUpdated
{
    /**
     * @param  list<string>  $changedAttributes
     */
    public function __construct(
        public User $user,
        public array $changedAttributes,
    ) {}
}
