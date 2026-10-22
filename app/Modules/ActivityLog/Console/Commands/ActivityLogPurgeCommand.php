<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Console\Commands;

use App\Modules\ActivityLog\Enums\ActivityLogName;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Activitylog\Support\Config;

#[Description('Deletes activity log records older than the retention configured for each log.')]
#[Signature('activity-log:purge')]
class ActivityLogPurgeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        foreach (ActivityLogName::cases() as $activityLogName) {
            $retentionDays = $activityLogName->retentionDays();

            if ($retentionDays <= 0) {
                $this->error("The retention of the {$activityLogName->value} log must be a positive integer, got {$retentionDays}.");

                return self::INVALID;
            }
        }

        foreach (ActivityLogName::cases() as $activityLogName) {
            $retentionDays = $activityLogName->retentionDays();

            $deletedCount = Config::cleanActivityLogAction()->execute($retentionDays, $activityLogName->value);

            $this->info("Purged {$deletedCount} {$activityLogName->value} activities older than {$retentionDays} days.");
        }

        return self::SUCCESS;
    }
}
