<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Enums\Events;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;

enum ActivityLogAuthEvent: string implements ActivityLogEvent
{
    case UserLoggedIn = 'auth.login';

    case UserLoggedOut = 'auth.logout';

    case LoginFailed = 'auth.login_failed';

    case PasswordUpdated = 'auth.password_updated';

    case PasswordReset = 'auth.password_reset';

    case TwoFactorConfirmed = 'auth.two_factor_confirmed';

    case TwoFactorDisabled = 'auth.two_factor_disabled';

    case RecoveryCodesRegenerated = 'auth.recovery_codes_regenerated';

    public function eventName(): string
    {
        return $this->value;
    }

    public function logName(): ActivityLogName
    {
        return ActivityLogName::Auth;
    }
}
