<?php

declare(strict_types=1);

use App\Modules\Admin\Jobs\RunBackupCleanup;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

it('runs the backup cleanup command', function (): void {
    Artisan::expects('call')
        ->once()
        ->with('backup:clean')
        ->andReturn(Command::SUCCESS);

    (new RunBackupCleanup)->handle();
});

it('fails when the backup cleanup command fails', function (): void {
    Artisan::expects('call')
        ->once()
        ->with('backup:clean')
        ->andReturn(Command::FAILURE);

    expect(fn () => (new RunBackupCleanup)->handle())
        ->toThrow(RuntimeException::class, 'The backup cleanup command failed.');
});
