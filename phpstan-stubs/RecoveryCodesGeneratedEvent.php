<?php

declare(strict_types=1);

namespace Laravel\Fortify\Events;

use App\User\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * PHPStan stub: Fortify's `RecoveryCodesGenerated` declares the $user
 * property with `@var \App\Models\User` (Laravel's default location). This
 * project uses `App\User\Models\User` (modular layout), so the original
 * docblock makes PHPStan infer the property as an unknown class and
 * breaks method resolution inside listeners that intersect with this event.
 *
 * The stub overrides the type to the project's actual User model so PHPStan
 * can narrow the type correctly without changing vendor code. The original
 * event class does NOT extend `TwoFactorAuthenticationEvent`, so it needs
 * its own dedicated stub.
 */
class RecoveryCodesGenerated
{
    use Dispatchable;

    /**
     * @var User
     */
    public $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
