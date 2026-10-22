<?php

declare(strict_types=1);

namespace App\Modules\Admin\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

final class RunBackupCleanup implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $exitCode = Artisan::call('backup:clean');

        throw_if($exitCode !== Command::SUCCESS, RuntimeException::class, 'The backup cleanup command failed.');
    }
}
