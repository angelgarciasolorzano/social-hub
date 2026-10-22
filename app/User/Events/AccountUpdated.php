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

    /**
     * Build the event from the attributes the last save changed, leaving out the timestamp.
     */
    public static function fromSavedChanges(User $user): self
    {
        return new self($user, array_values(array_diff(array_keys($user->getChanges()), ['updated_at'])));
    }
}
