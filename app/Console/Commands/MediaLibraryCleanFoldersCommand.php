<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\Prohibitable;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

#[Description('Deletes all folders and files inside storage/app/public and public/storage. For local development use only!')]
#[Signature('app:clean-media-library-folders')]
class MediaLibraryCleanFoldersCommand extends Command
{
    use Prohibitable;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->isProhibited()) {
            return self::FAILURE;
        }

        $paths = [
            storage_path('app/public'),
            public_path('storage'),
        ];

        $totalDeletedFolder = 0;
        $totalDeletedFile = 0;

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                $this->warn(\sprintf('The path %s does not exist.', $path));

                continue;
            }

            /** @var string[] $folders */
            $folders = File::directories($path);

            foreach ($folders as $folder) {
                /** @var string $folder */
                File::deleteDirectory($folder);
                $this->info('Deleted folder: '.$folder);
                $totalDeletedFolder++;
            }

            /** @var SplFileInfo[] $files */
            $files = File::files($path);

            foreach ($files as $file) {
                File::delete($file->getPathname());
                $this->info('Deleted file: '.$file->getPathname());
                $totalDeletedFile++;
            }
        }

        $this->info('Total deleted folders: '.$totalDeletedFolder);
        $this->info('Total deleted files: '.$totalDeletedFile);

        return self::SUCCESS;
    }
}
