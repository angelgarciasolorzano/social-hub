<?php

declare(strict_types=1);

namespace App\Modules\Admin\Jobs;

use App\Modules\Admin\Services\DashboardBackupSummaryCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

final class RunBackupCleanup implements ShouldQueue
{
    use Queueable;

    public function handle(DashboardBackupSummaryCache $dashboardBackupSummaryCache): void
    {
        try {
            $exitCode = Artisan::call('backup:clean');
        } finally {
            $dashboardBackupSummaryCache->forget();
        }

        throw_if($exitCode !== Command::SUCCESS, RuntimeException::class, 'The backup cleanup command failed.');
    }
}
