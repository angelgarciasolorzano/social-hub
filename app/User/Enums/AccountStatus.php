<?php

declare(strict_types=1);

namespace App\User\Enums;

enum AccountStatus: string
{
    /**
     * Message shown by every sign-in path (password, 2FA challenge, open sessions)
     * when the account is suspended.
     */
    public const string SUSPENDED_LOGIN_MESSAGE = 'Tu cuenta está suspendida. Contacta al equipo de soporte.';

    case Active = 'active';

    case Suspended = 'suspended';

    /**
     * Readable name shown in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }
}
