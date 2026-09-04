<?php

declare(strict_types=1);

namespace App\Auth\Console\Commands;

use App\Auth\Models\TrustedDevice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Force-deletes trusted devices soft-deleted more than N days ago (GDPR retention).')]
#[Signature('trusted-devices:purge {--days= : Override the configured purge_after_days}')]
class TrustedDevicePurge extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var int $configuredDays */
        $configuredDays = config('module.auth.trusted_devices.purge_after_days');

        $daysOption = $this->option('days');

        $purgeAfterDays = is_string($daysOption) && $daysOption !== ''
            ? (int) $daysOption
            : $configuredDays;

        if ($purgeAfterDays <= 0) {
            $this->error("purge_after_days must be a positive integer, got {$purgeAfterDays}.");

            return self::INVALID;
        }

        $cutoff = CarbonImmutable::now()->subDays($purgeAfterDays);

        $rawDeletedCount = TrustedDevice::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->forceDelete();

        $purgedCount = is_int($rawDeletedCount) ? $rawDeletedCount : 0;

        $this->info(sprintf(
            'Purged %d trusted devices soft-deleted before %s.',
            $purgedCount,
            $cutoff->toIso8601String(),
        ));

        return self::SUCCESS;
    }
}
