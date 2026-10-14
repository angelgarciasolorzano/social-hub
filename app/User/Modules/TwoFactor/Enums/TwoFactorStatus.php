<?php

declare(strict_types=1);

namespace App\User\Modules\TwoFactor\Enums;

use App\User\Models\User;

enum TwoFactorStatus: string
{
    case Disabled = 'disabled';

    case Pending = 'pending';

    case Confirmed = 'confirmed';

    /**
     * Resolve the state of the user's two-factor setup: no secret is disabled,
     * a secret without confirmation is pending, and both together is confirmed.
     */
    public static function fromUser(User $user): self
    {
        if ($user->two_factor_secret === null) {
            return self::Disabled;
        }

        if ($user->two_factor_confirmed_at === null) {
            return self::Pending;
        }

        return self::Confirmed;
    }
}
