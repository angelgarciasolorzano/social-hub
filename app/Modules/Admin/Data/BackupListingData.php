<?php

declare(strict_types=1);

namespace App\Modules\Admin\Data;

use Spatie\LaravelData\Data;

final class BackupListingData extends Data
{
    /**
     * @param  list<array{
     *     filename: string,
     *     created_at: string|null,
     *     size_in_bytes: float|null,
     *     disk: string,
     *     available: bool
     * }>  $backups
     * @param  array{healthy: bool, failed_checks: list<string>}  $health
     */
    public function __construct(
        public array $backups,
        public array $health,
    ) {}
}
