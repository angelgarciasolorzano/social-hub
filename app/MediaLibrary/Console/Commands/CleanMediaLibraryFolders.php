<?php

namespace App\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanMediaLibraryFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-media-library-folders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes all folders and files inside storage/app/public and public/storage. For local development use only!';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $paths = [
            storage_path('app/public'),
            public_path('storage'),
        ];

        $totalDeleted = 0;

        foreach ($paths as $mediaPath) {
            if (! File::exists($mediaPath)) {
                $this->warn("The path {$mediaPath} does not exist.");

                continue;
            }

            $folders = File::directories($mediaPath);

            foreach ($folders as $folder) {
                /** @var string $folder */
                File::deleteDirectory($folder);
                $this->info("Deleted folder: {$folder}");
                $totalDeleted++;
            }

            $files = File::files($mediaPath);

            foreach ($files as $file) {
                File::delete($file);
                $this->info("Deleted file: {$file}");
                $totalDeleted++;
            }
        }

        $this->info("Total deleted folders and files: {$totalDeleted}");

        return self::SUCCESS;
    }
}
