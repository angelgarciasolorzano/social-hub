<?php

declare(strict_types=1);

use App\Modules\Admin\Jobs\RunBackupCleanup;
use App\Modules\Admin\Services\DashboardBackupSummaryCache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command;

it('runs the backup cleanup command', function (): void {
    Cache::put(DashboardBackupSummaryCache::KEY, 'stale summary');
    Artisan::expects('call')
        ->once()
        ->with('backup:clean')
        ->andReturn(Command::SUCCESS);

    (new RunBackupCleanup)->handle(resolve(DashboardBackupSummaryCache::class));

    expect(Cache::has(DashboardBackupSummaryCache::KEY))->toBeFalse();
});

it('fails when the backup cleanup command fails', function (): void {
    Cache::put(DashboardBackupSummaryCache::KEY, 'stale summary');
    Artisan::expects('call')
        ->once()
        ->with('backup:clean')
        ->andReturn(Command::FAILURE);

    expect(fn () => (new RunBackupCleanup)->handle(resolve(DashboardBackupSummaryCache::class)))
        ->toThrow(RuntimeException::class, 'The backup cleanup command failed.')
        ->and(Cache::has(DashboardBackupSummaryCache::KEY))
        ->toBeFalse();
});
