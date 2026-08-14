<?php

declare(strict_types=1);

namespace App\User\TwoFactor\Listeners;

use Carbon\CarbonImmutable;
use Laravel\Fortify\Events\RecoveryCodesGenerated;

final readonly class TrackRecoveryCodesRegeneration
{
    public function handle(RecoveryCodesGenerated $recoveryCodesGenerated): void
    {
        $recoveryCodesGenerated->user->forceFill([
            'recovery_codes_regenerated_at' => CarbonImmutable::now(),
        ])->save();
    }
}
