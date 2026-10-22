<?php

declare(strict_types=1);

it('stores backups on a dedicated private S3 disk', function (): void {
    expect(config('filesystems.disks.backups'))
        ->toMatchArray([
            'driver' => 's3',
            'visibility' => 'private',
        ])
        ->and(config('backup.backup.destination.disks'))->toBe(['backups'])
        ->and(config('backup.monitor_backups.0.disks'))->toBe(['backups'])
        ->and(config('filesystems.default'))->not->toBe('backups');
});

it('backs up only the MySQL database and excludes files', function (): void {
    expect(config('backup.backup.source.databases'))->toBe(['mysql'])
        ->and(config('backup.backup.source.files.include'))->toBe([]);
});

it('uses a single transaction for MySQL dumps only', function (): void {
    expect(config('database.connections.mysql.dump'))
        ->toMatchArray(['useSingleTransaction' => true])
        ->and(config('database.connections.sqlite'))->not->toHaveKey('dump')
        ->and(config('database.connections.mariadb'))->not->toHaveKey('dump')
        ->and(config('database.connections.pgsql'))->not->toHaveKey('dump')
        ->and(config('database.connections.sqlsrv'))->not->toHaveKey('dump');
});

it('encrypts and verifies each backup archive', function (): void {
    expect(config('backup.backup.encryption'))->toBe('aes256')
        ->and(config('backup.backup.verify_backup'))->toBeTrue();
});

it('keeps the configured daily weekly and monthly retention windows', function (): void {
    expect(config('backup.cleanup.default_strategy'))->toBe([
        'keep_all_backups_for_days' => 7,
        'keep_daily_backups_for_days' => 0,
        'keep_weekly_backups_for_weeks' => 4,
        'keep_monthly_backups_for_months' => 6,
        'keep_yearly_backups_for_years' => 0,
        'delete_oldest_backups_when_using_more_megabytes_than' => null,
    ]);
});
