<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\MySqlDatabaseCreator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use PDOException;

#[Signature('db:create-application')]
#[Description('Create the MySQL database configured for the application')]
final class CreateApplicationDatabaseCommand extends Command
{
    public function __construct(private readonly MySqlDatabaseCreator $mySqlDatabaseCreator)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->components->error('This command must run in the local environment.');

            return self::FAILURE;
        }

        if (config('database.default') !== 'mysql') {
            $this->components->error('The application database connection must be MySQL.');

            return self::FAILURE;
        }

        try {
            $database = $this->mySqlDatabaseCreator->createDatabase(config('database.connections.mysql'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error(\sprintf('The application database configuration %s.', $exception->getMessage()));

            return self::FAILURE;
        } catch (PDOException) {
            $this->components->error('The application database could not be created. Check the MySQL connection and database-creation privileges.');

            return self::FAILURE;
        }

        $this->components->info("Application database [{$database}] is ready.");

        return self::SUCCESS;
    }
}
