<?php

declare(strict_types=1);

namespace App\Modules\Admin\Data;

use Spatie\LaravelData\Data;

final class BackupSummaryData extends Data
{
    public function __construct(
        public bool $listingAvailable,
        public int $availableCount,
        public int $missingCount,
        public bool $hasBackups,
        public bool $destinationHealthy,
        public ?string $latestBackupAge,
        public ?string $latestBackupCreatedAt,
        public ?float $totalSizeInBytes,
    ) {}
}
