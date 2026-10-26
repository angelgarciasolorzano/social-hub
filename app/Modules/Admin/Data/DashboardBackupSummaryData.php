<?php

declare(strict_types=1);

namespace App\Modules\Admin\Data;

use Spatie\LaravelData\Data;

final class DashboardBackupSummaryData extends Data
{
    public function __construct(
        public int $availableCount,
        public int $totalCount,
        public ?string $latestCreatedAt,
        public bool $healthy,
    ) {}
}
