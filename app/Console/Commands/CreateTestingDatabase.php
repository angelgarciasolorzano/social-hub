<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PDO;
use PDOException;

#[Signature('db:create-testing')]
#[Description('Create the MySQL database used by the test suite')]
final class CreateTestingDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! app()->environment('testing')) {
            $this->components->error('This command must run in the testing environment. Use --env=testing.');

            return self::FAILURE;
        }

        $database = config('database.connections.mysql.database');
        $charset = config('database.connections.mysql.charset', 'utf8mb4');
        $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $socket = config('database.connections.mysql.unix_socket', '');
        $username = config('database.connections.mysql.username', 'root');
        $password = config('database.connections.mysql.password', '');

        if (! is_string($database)) {
            $this->components->error('The testing database configuration must contain string values.');

            return self::FAILURE;
        }

        if (! $this->isValidIdentifier($database) || ! $this->isValidIdentifier($charset) || ! $this->isValidIdentifier($collation)) {
            $this->components->error('The testing database configuration contains an invalid identifier.');

            return self::FAILURE;
        }

        $options = config('database.connections.mysql.options', []);

        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

        $dsn = $socket !== ''
            ? "mysql:unix_socket={$socket};charset={$charset}"
            : "mysql:host={$host};port={$port};charset={$charset}";

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET `%s` COLLATE `%s`',
                $this->escapeIdentifier($database),
                $this->escapeIdentifier($charset),
                $this->escapeIdentifier($collation),
            ));
        } catch (PDOException) {
            $this->components->error('The testing database could not be created. Check the MySQL testing credentials.');

            return self::FAILURE;
        }

        $this->components->info("Testing database [{$database}] is ready.");

        return self::SUCCESS;
    }

    private function isValidIdentifier(string $identifier): bool
    {
        return $identifier !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $identifier) === 1;
    }

    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }
}
