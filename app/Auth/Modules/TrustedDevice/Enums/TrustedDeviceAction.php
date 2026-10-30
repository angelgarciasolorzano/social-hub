<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Enums;

use App\Support\Translation\TranslatedText;

enum TrustedDeviceAction: string
{
    case Created = 'created';

    case Renewed = 'renewed';

    case Renamed = 'renamed';

    case Revoked = 'revoked';

    case RevokedAll = 'revoked_all';

    case Reactivated = 'reactivated';

    /**
     * The name shown in the activity of the trusted devices page, in the active language.
     */
    public function label(): string
    {
        return TranslatedText::of("setting/trusted-devices.actions.{$this->value}");
    }
}
