<?php

declare(strict_types=1);

use App\Console\Commands\MediaLibraryCleanFoldersCommand;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;

/**
 * Run the callback with storage/ and public/ redirected to a temporary directory,
 * so the command can never touch the project's real files, and restore everything afterwards.
 *
 * @param  Closure(string): void  $callback  Receives the temporary root directory.
 */
function withTemporaryMediaPaths(Application $application, Closure $callback): void
{
    $originalStoragePath = $application->storagePath();
    $originalPublicPath = $application->publicPath();
    $originalEnvironment = $application->environment();
    $root = sys_get_temp_dir().'/media-library-clean-'.uniqid('', true);

    File::makeDirectory($root.'/storage/app/public', 0755, true);
    File::makeDirectory($root.'/public', 0755, true);

    $application->useStoragePath($root.'/storage');
    $application->usePublicPath($root.'/public');

    try {
        $callback($root);
    } finally {
        $application->useStoragePath($originalStoragePath);
        $application->usePublicPath($originalPublicPath);
        $application->instance('env', $originalEnvironment);
        MediaLibraryCleanFoldersCommand::prohibit(false);
        File::deleteDirectory($root);
    }
}

it('deletes the folders and files of both media paths and reports the totals', function (): void {
    withTemporaryMediaPaths($this->app, function (string $root): void {
        File::makeDirectory($root.'/storage/app/public/posts/1', 0755, true);
        File::put($root.'/storage/app/public/posts/1/photo.jpg', 'image');
        File::makeDirectory($root.'/storage/app/public/2', 0755, true);
        File::put($root.'/storage/app/public/root.txt', 'file');

        File::makeDirectory($root.'/public/storage/cache', 0755, true);
        File::put($root.'/public/storage/other.txt', 'file');

        $this->artisan('app:clean-media-library-folders')
            ->expectsOutputToContain('Total deleted folders: 3')
            ->expectsOutputToContain('Total deleted files: 2')
            ->assertSuccessful();

        expect(File::directories($root.'/storage/app/public'))
            ->toBeEmpty()
            ->and(File::files($root.'/storage/app/public'))
            ->toBeEmpty()
            ->and(File::directories($root.'/public/storage'))
            ->toBeEmpty()
            ->and(File::files($root.'/public/storage'))
            ->toBeEmpty();
    });
});

it('warns about a path that does not exist and still succeeds', function (): void {
    withTemporaryMediaPaths($this->app, function (string $root): void {
        File::put($root.'/storage/app/public/root.txt', 'file');

        $this->artisan('app:clean-media-library-folders')
            ->expectsOutputToContain('does not exist')
            ->expectsOutputToContain('Total deleted files: 1')
            ->assertSuccessful();

        expect(File::exists($root.'/storage/app/public/root.txt'))->toBeFalse();
    });
});

it('refuses to run when the command is prohibited and deletes nothing', function (): void {
    withTemporaryMediaPaths($this->app, function (string $root): void {
        File::makeDirectory($root.'/storage/app/public/posts/1', 0755, true);
        File::put($root.'/storage/app/public/posts/1/photo.jpg', 'image');

        MediaLibraryCleanFoldersCommand::prohibit();

        $this->artisan('app:clean-media-library-folders')
            ->expectsOutputToContain('prohibited')
            ->assertFailed();

        expect(File::exists($root.'/storage/app/public/posts/1/photo.jpg'))->toBeTrue();
    });
});

it('is prohibited when the application boots in production', function (): void {
    withTemporaryMediaPaths($this->app, function (string $root): void {
        File::put($root.'/storage/app/public/root.txt', 'file');

        $this->app->instance('env', 'production');
        new AppServiceProvider($this->app)->boot();

        $this->artisan('app:clean-media-library-folders')->assertFailed();

        expect(File::exists($root.'/storage/app/public/root.txt'))->toBeTrue();
    });
});

it('is allowed when the application boots outside production', function (): void {
    withTemporaryMediaPaths($this->app, function (string $root): void {
        File::put($root.'/storage/app/public/root.txt', 'file');

        $this->app->instance('env', 'local');
        new AppServiceProvider($this->app)->boot();

        $this->artisan('app:clean-media-library-folders')->assertSuccessful();

        expect(File::exists($root.'/storage/app/public/root.txt'))->toBeFalse();
    });
});
