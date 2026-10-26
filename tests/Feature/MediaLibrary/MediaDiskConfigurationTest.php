<?php

declare(strict_types=1);

const MEDIA_DISK_ENVIRONMENT = [
    'AWS_ACCESS_KEY_ID' => 'general-key',
    'AWS_SECRET_ACCESS_KEY' => 'general-secret',
    'AWS_DEFAULT_REGION' => 'us-east-1',
    'AWS_ENDPOINT' => 'http://localhost:4566',
    'AWS_USE_PATH_STYLE_ENDPOINT' => 'true',
    'AWS_BUCKET' => 'general-bucket',
    'AWS_URL' => 'http://localhost:4566/general-bucket',
    'MEDIA_S3_BUCKET' => 'media-bucket',
    'MEDIA_S3_URL' => 'http://localhost:4566/media-bucket',
];

it('stores media in its own bucket and serves it from its own url', function (): void {
    $disk = configWithEnvironment('filesystems', MEDIA_DISK_ENVIRONMENT)['disks']['media'];

    expect($disk)->toMatchArray([
        'bucket' => 'media-bucket',
        'url' => 'http://localhost:4566/media-bucket',
    ]);
});

it('connects to S3 with the general AWS credentials and endpoint', function (): void {
    $disk = configWithEnvironment('filesystems', MEDIA_DISK_ENVIRONMENT)['disks']['media'];

    expect($disk)->toMatchArray([
        'key' => 'general-key',
        'secret' => 'general-secret',
        'region' => 'us-east-1',
        'endpoint' => 'http://localhost:4566',
        'use_path_style_endpoint' => true,
    ]);
});

it('is a public S3 disk', function (): void {
    expect(config('filesystems.disks.media'))->toMatchArray([
        'driver' => 's3',
        'visibility' => 'public',
    ]);
});

it('keeps the tests on the public disk', function (): void {
    expect(config('media-library.disk_name'))->toBe('public');
});

it('leaves the environment as it was after reading the config', function (): void {
    $bucketBefore = env('MEDIA_S3_BUCKET');

    configWithEnvironment('filesystems', MEDIA_DISK_ENVIRONMENT);

    expect(env('MEDIA_S3_BUCKET'))->toBe($bucketBefore);
});
