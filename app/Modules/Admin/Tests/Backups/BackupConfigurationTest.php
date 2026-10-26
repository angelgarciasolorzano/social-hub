<?php

declare(strict_types=1);

const GENERAL_AWS_ENVIRONMENT = [
    'AWS_ACCESS_KEY_ID' => 'general-key',
    'AWS_SECRET_ACCESS_KEY' => 'general-secret',
    'AWS_DEFAULT_REGION' => 'us-east-1',
    'AWS_ENDPOINT' => 'http://localhost:4566',
    'AWS_USE_PATH_STYLE_ENDPOINT' => 'true',
    'AWS_BUCKET' => 'general-bucket',
];

const BACKUP_CONNECTION_VARIABLES_REMOVED = [
    'BACKUP_S3_ACCESS_KEY_ID' => null,
    'BACKUP_S3_SECRET_ACCESS_KEY' => null,
    'BACKUP_S3_DEFAULT_REGION' => null,
    'BACKUP_S3_ENDPOINT' => null,
    'BACKUP_S3_USE_PATH_STYLE_ENDPOINT' => null,
];

it('stores backups on a dedicated private S3 disk', function (): void {
    expect(config('filesystems.disks.backups'))
        ->toMatchArray([
            'driver' => 's3',
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ])
        ->and(config('backup.backup.destination.disks'))->toBe(['backups'])
        ->and(config('backup.monitor_backups.0.disks'))->toBe(['backups'])
        ->and(config('filesystems.default'))->not->toBe('backups');
});

it('falls back to the general AWS credentials and endpoint when no backup ones are set', function (): void {
    $disk = filesystemDiskWithEnvironment('backups', [
        ...GENERAL_AWS_ENVIRONMENT,
        ...BACKUP_CONNECTION_VARIABLES_REMOVED,
        'BACKUP_S3_BUCKET' => 'backups-bucket',
    ]);

    expect($disk)->toMatchArray([
        'key' => 'general-key',
        'secret' => 'general-secret',
        'region' => 'us-east-1',
        'endpoint' => 'http://localhost:4566',
        'use_path_style_endpoint' => true,
    ]);
});

it('prefers its own backup credentials and endpoint when they are set', function (): void {
    $disk = filesystemDiskWithEnvironment('backups', [
        ...GENERAL_AWS_ENVIRONMENT,
        'BACKUP_S3_ACCESS_KEY_ID' => 'backup-key',
        'BACKUP_S3_SECRET_ACCESS_KEY' => 'backup-secret',
        'BACKUP_S3_DEFAULT_REGION' => 'eu-west-1',
        'BACKUP_S3_ENDPOINT' => 'https://backups.example.test',
        'BACKUP_S3_USE_PATH_STYLE_ENDPOINT' => 'false',
        'BACKUP_S3_BUCKET' => 'backups-bucket',
    ]);

    expect($disk)->toMatchArray([
        'key' => 'backup-key',
        'secret' => 'backup-secret',
        'region' => 'eu-west-1',
        'endpoint' => 'https://backups.example.test',
        'use_path_style_endpoint' => false,
    ]);
});

it('never stores backups in the general bucket', function (?string $backupBucket): void {
    $disk = filesystemDiskWithEnvironment('backups', [
        ...GENERAL_AWS_ENVIRONMENT,
        ...BACKUP_CONNECTION_VARIABLES_REMOVED,
        'BACKUP_S3_BUCKET' => $backupBucket,
    ]);

    expect($disk['bucket'])->toBe($backupBucket);
})->with([
    'its own bucket' => ['backups-bucket'],
    'no bucket configured' => [null],
]);

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
