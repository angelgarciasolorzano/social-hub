<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Enums;

use App\Support\Translation\TranslatedText;

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

    case TwoFactorRequired;

    public function isSuccessful(): bool
    {
        return match ($this) {
            self::AlreadyActive, self::AlreadyRevoked, self::TwoFactorRequired => false,
            self::Created,
            self::Renamed,
            self::Renewed,
            self::Revoked,
            self::RevokedAll,
            self::Reactivated,
            self::ForceDeleted => true,
        };
    }

    /**
     * The flash text of the result, in the active language.
     */
    public function message(): string
    {
        return TranslatedText::of(match ($this) {
            self::Created => 'setting/trusted-devices.results.created',
            self::AlreadyActive => 'setting/trusted-devices.results.already_active',
            self::AlreadyRevoked => 'setting/trusted-devices.results.already_revoked',
            self::Renamed => 'setting/trusted-devices.results.renamed',
            self::Renewed => 'setting/trusted-devices.results.renewed',
            self::Revoked => 'setting/trusted-devices.results.revoked',
            self::RevokedAll => 'setting/trusted-devices.results.revoked_all',
            self::Reactivated => 'setting/trusted-devices.results.reactivated',
            self::ForceDeleted => 'setting/trusted-devices.results.force_deleted',
            self::TwoFactorRequired => 'setting/trusted-devices.results.two_factor_required',
        });
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
