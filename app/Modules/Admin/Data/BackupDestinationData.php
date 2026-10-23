<?php

declare(strict_types=1);

namespace App\Modules\Admin\Data;

use Spatie\LaravelData\Data;

final class BackupDestinationData extends Data
{
    /**
     * @param  array{
     *     keep_all_backups_for_days: int,
     *     keep_daily_backups_for_days: int,
     *     keep_weekly_backups_for_weeks: int,
     *     keep_monthly_backups_for_months: int,
     *     keep_yearly_backups_for_years: int
     * }  $retention
     */
    public function __construct(
        public string $provider,
        public ?string $bucket,
        public string $prefix,
        public ?string $region,
        public string $visibility,
        public ?string $archiveEncryption,
        public array $retention,
    ) {}
}
