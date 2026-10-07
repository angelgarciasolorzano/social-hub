<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Enums;

enum TrustedDeviceOperationResult
{
    case Created;

    case AlreadyActive;

    case AlreadyRevoked;

    case Renamed;

    case Renewed;

    case Revoked;

    case RevokedAll;

    case Reactivated;

    case ForceDeleted;

    public function isSuccessful(): bool
    {
        return match ($this) {
            self::AlreadyActive, self::AlreadyRevoked => false,
            self::Created,
            self::Renamed,
            self::Renewed,
            self::Revoked,
            self::RevokedAll,
            self::Reactivated,
            self::ForceDeleted => true,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::Created => 'Dispositivo agregado correctamente.',
            self::AlreadyActive => 'Este dispositivo ya está registrado como de confianza.',
            self::AlreadyRevoked => 'Este dispositivo ya está registrado pero fue revocado. Reactívalo desde la lista de dispositivos revocados en lugar de agregarlo nuevamente.',
            self::Renamed => 'Dispositivo renombrado correctamente.',
            self::Renewed => 'Confianza renovada correctamente.',
            self::Revoked => 'Dispositivo de confianza revocado correctamente.',
            self::RevokedAll => 'Todos los dispositivos de confianza fueron revocados.',
            self::Reactivated => 'Dispositivo reactivado correctamente. Se regeneró el token de confianza por seguridad.',
            self::ForceDeleted => 'Dispositivo eliminado permanentemente.',
        };
    }

    /**
     * @return array{type: string, message: string}
     */
    public function payload(): array
    {
        return [
            'type' => $this->isSuccessful() ? 'success' : 'error',
            'message' => $this->message(),
        ];
    }
}
