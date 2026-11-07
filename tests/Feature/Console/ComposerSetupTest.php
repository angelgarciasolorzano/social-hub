<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('creates both MySQL databases before running migrations during setup', function (): void {
    /** @var array{scripts: array{setup: list<string>}} $composerConfiguration */
    $composerConfiguration = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    $setupCommands = $composerConfiguration['scripts']['setup'];
    $applicationDatabaseCommand = '@php artisan db:create-application --no-interaction';
    $testingDatabaseCommand = '@php artisan --env=testing db:create-testing';
    $migrationCommand = '@php artisan migrate --force';

    expect($setupCommands)->toContain($applicationDatabaseCommand, $testingDatabaseCommand, $migrationCommand)
        ->and(array_search($applicationDatabaseCommand, $setupCommands, true))
        ->toBeLessThan(array_search($testingDatabaseCommand, $setupCommands, true))
        ->and(array_search($testingDatabaseCommand, $setupCommands, true))
        ->toBeLessThan(array_search($migrationCommand, $setupCommands, true));
});

it('creates the application MySQL database before migrations for a new project', function (): void {
    /** @var array{scripts: array{'post-create-project-cmd': list<string>}} $composerConfiguration */
    $composerConfiguration = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    $projectCreationCommands = $composerConfiguration['scripts']['post-create-project-cmd'];
    $applicationDatabaseCommand = '@php artisan db:create-application --no-interaction';
    $migrationCommand = '@php artisan migrate --graceful --ansi';

    expect($projectCreationCommands)->toContain($applicationDatabaseCommand, $migrationCommand)
        ->and(array_search($applicationDatabaseCommand, $projectCreationCommands, true))
        ->toBeLessThan(array_search($migrationCommand, $projectCreationCommands, true));
});
