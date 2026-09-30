<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Data;

use Spatie\LaravelData\Data;

final class TrustedDeviceDashboardStatsData extends Data
{
    /** @param array{desktop: int, mobile: int} $byDeviceType */
    public function __construct(
        public int $total,
        public int $active,
        public int $expiringSoon,
        public int $recentlyAdded,
        public int $inactive,
        public int $revoked,
        public array $byDeviceType,
    ) {}
}
