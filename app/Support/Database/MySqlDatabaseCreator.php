<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\ConfigurationUrlParser;
use InvalidArgumentException;
use PDO;

final class MySqlDatabaseCreator
{
    /**
     * Create the database configured for the MySQL connection.
     *
     * @param  array<string, mixed>  $connection
     */
    public function createDatabase(array $connection): string
    {
        $url = $connection['url'] ?? null;

        throw_if(! is_null($url) && ! \is_string($url), InvalidArgumentException::class, 'must contain a string DB_URL');

        $connection = (new ConfigurationUrlParser)->parseConfiguration($connection);
        $driver = $connection['driver'] ?? null;

        throw_if($driver !== 'mysql', InvalidArgumentException::class, 'must resolve to a MySQL connection');

        $database = $connection['database'] ?? null;
        $charset = $connection['charset'] ?? 'utf8mb4';
        $collation = $connection['collation'] ?? 'utf8mb4_unicode_ci';

        throw_if(! \is_string($database) || ! \is_string($charset) || ! \is_string($collation), InvalidArgumentException::class, 'must contain string values');

        throw_if(! $this->isValidIdentifier($database) || ! $this->isValidIdentifier($charset) || ! $this->isValidIdentifier($collation), InvalidArgumentException::class, 'contains an invalid identifier');

        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? '3306';
        $socket = $connection['unix_socket'] ?? '';
        $username = $connection['username'] ?? 'root';
        $password = $connection['password'] ?? '';
        $options = $connection['options'] ?? [];

        throw_if(! \is_string($host) || (! \is_string($port) && ! \is_int($port)) || ! \is_string($socket) || ! \is_string($username) || ! \is_string($password), InvalidArgumentException::class, 'must contain string values');

        $port = (string) $port;

        throw_if(str_contains($host, ';') || ! ctype_digit($port) || str_contains($socket, ';'), InvalidArgumentException::class, 'contains an invalid connection address');

        throw_unless(\is_array($options), InvalidArgumentException::class, 'contains invalid connection options');

        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

        $dsn = $socket !== ''
            ? "mysql:unix_socket={$socket};charset={$charset}"
            : "mysql:host={$host};port={$port};charset={$charset}";

        $pdo = new PDO($dsn, $username, $password, $options);

        $pdo->exec(\sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET `%s` COLLATE `%s`',
            $this->escapeIdentifier($database),
            $this->escapeIdentifier($charset),
            $this->escapeIdentifier($collation),
        ));

        return $database;
    }

    /**
     * Check whether a database, charset, or collation can be safely quoted as an identifier.
     */
    private function isValidIdentifier(string $identifier): bool
    {
        return $identifier !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $identifier) === 1;
    }

    /**
     * Escape a MySQL identifier.
     */
    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }
}
