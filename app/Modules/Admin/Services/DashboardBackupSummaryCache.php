<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\Cache;

final class DashboardBackupSummaryCache
{
    public const string KEY = 'admin:dashboard:backup-summary:v1';

    public const int TTL_MINUTES = 5;

    /** Remove the cached dashboard backup summary. */
    public function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
