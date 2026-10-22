<?php

declare(strict_types=1);

namespace App\User\Events;

use App\User\Enums\UserImageType;
use App\User\Models\User;

/**
 * Dispatched after a user replaces their profile picture or cover image.
 */
final readonly class AccountImageUpdated
{
    public function __construct(
        public User $user,
        public UserImageType $userImageType,
    ) {}
}
