<?php

declare(strict_types=1);

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\File;

it('includes Reverb, the queue listener, and Vite in development processes', function (): void {
    $developmentCommands = DevCommands::commands();
    $commandsByName = array_column($developmentCommands, 'command', 'name');

    expect(array_keys($commandsByName))->toContain('reverb', 'queue', 'vite')
        ->and($commandsByName['reverb'] ?? null)
        ->toBe('php artisan reverb:start')
        ->and($commandsByName['queue'] ?? null)
        ->toContain('queue:listen');
});

it('selects Reverb as the broadcast connection in the environment example', function (): void {
    $environmentExample = File::get(base_path('.env.example'));

    $broadcastConnectionLine = collect(explode(PHP_EOL, $environmentExample))
        ->first(fn (string $line): bool => str_starts_with($line, 'BROADCAST_CONNECTION='));

    expect($broadcastConnectionLine)->toBe('BROADCAST_CONNECTION=reverb');
});
