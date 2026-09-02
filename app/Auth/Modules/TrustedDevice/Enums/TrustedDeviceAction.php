<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Enums;

enum TrustedDeviceAction: string
{
    case Created = 'created';

    case Renewed = 'renewed';

    case Renamed = 'renamed';

    case Revoked = 'revoked';

    case RevokedAll = 'revoked_all';

    case Reactivated = 'reactivated';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Dispositivo agregado',
            self::Renewed => 'Confianza renovada',
            self::Renamed => 'Dispositivo renombrado',
            self::Revoked => 'Dispositivo revocado',
            self::RevokedAll => 'Todos los dispositivos revocados',
            self::Reactivated => 'Dispositivo re-confiado',
        };
    }
}
