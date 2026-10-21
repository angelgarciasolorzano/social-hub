<?php

declare(strict_types=1);

namespace App\Auth\Modules\Password\Events;

use App\User\Models\User;

/**
 * Dispatched after an authenticated user changes their own password.
 */
final readonly class PasswordUpdated
{
    public function __construct(public User $user) {}
}
