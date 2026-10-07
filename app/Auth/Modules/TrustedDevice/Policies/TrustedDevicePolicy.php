<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Policies;

use App\Auth\Models\TrustedDevice;
use App\User\Models\User;

final class TrustedDevicePolicy
{
    public function update(User $user, TrustedDevice $trustedDevice): bool
    {
        return $trustedDevice->user_id === $user->getKey();
    }

    public function delete(User $user, TrustedDevice $trustedDevice): bool
    {
        return $trustedDevice->user_id === $user->getKey();
    }

    public function restore(User $user, TrustedDevice $trustedDevice): bool
    {
        return $trustedDevice->user_id === $user->getKey();
    }

    public function forceDelete(User $user, TrustedDevice $trustedDevice): bool
    {
        return $trustedDevice->user_id === $user->getKey();
    }
}
