<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\MySqlDatabaseCreator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use PDOException;

#[Signature('db:create-testing')]
#[Description('Create the MySQL database used by the test suite')]
final class CreateTestingDatabase extends Command
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
        if (! app()->environment('testing')) {
            $this->components->error('This command must run in the testing environment. Use --env=testing.');

            return self::FAILURE;
        }

        try {
            $database = $this->mySqlDatabaseCreator->createDatabase(config('database.connections.mysql'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error(\sprintf('The testing database configuration %s.', $exception->getMessage()));

            return self::FAILURE;
        } catch (PDOException) {
            $this->components->error('The testing database could not be created. Check the MySQL testing credentials.');

            return self::FAILURE;
        }

        $this->components->info("Testing database [{$database}] is ready.");

        return self::SUCCESS;
    }
}
