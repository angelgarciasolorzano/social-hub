<?php

declare(strict_types=1);

/**
 * Open a MySQL connection without selecting a database.
 */
function applicationMysqlAdminPdo(): PDO
{
    /** @var array<string, mixed> $connection */
    $connection = config('database.connections.mysql');

    $host = $connection['host'] ?? '127.0.0.1';
    $port = $connection['port'] ?? '3306';
    $socket = $connection['unix_socket'] ?? '';
    $username = $connection['username'] ?? 'root';
    $password = $connection['password'] ?? '';

    $dsn = is_string($socket) && $socket !== ''
        ? "mysql:unix_socket={$socket}"
        : sprintf('mysql:host=%s;port=%s', $host, $port);

    return new PDO(
        $dsn,
        (string) $username,
        (string) $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

/**
 * Build a unique database name safe for use in MySQL identifiers.
 */
function applicationThrowawayDatabaseName(): string
{
    return 'social_hub_app_cmd_'.bin2hex(random_bytes(4));
}

it('refuses to run outside the local environment', function (): void {
    $this->app->instance('env', 'testing');

    $this->artisan('db:create-application')
        ->expectsOutputToContain('must run in the local environment')
        ->assertFailed();
});

it('refuses to create the application database when the default connection is not MySQL', function (): void {
    $this->app->instance('env', 'local');
    config(['database.default' => 'sqlite']);

    try {
        $this->artisan('db:create-application')
            ->expectsOutputToContain('connection must be MySQL')
            ->assertFailed();
    } finally {
        config(['database.default' => 'mysql']);
    }
});

it('rejects an invalid application database identifier before connecting to MySQL', function (): void {
    $this->app->instance('env', 'local');
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'invalid database',
    ]);

    $this->artisan('db:create-application')
        ->expectsOutputToContain('contains an invalid identifier')
        ->assertFailed();
});

it('fails with a generic message and never leaks the credentials when MySQL is unreachable', function (): void {
    $this->app->instance('env', 'local');
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.unix_socket' => '/nonexistent/mysql.sock',
        'database.connections.mysql.password' => 'super-secret-password',
    ]);

    $this->artisan('db:create-application')
        ->expectsOutputToContain('could not be created')
        ->doesntExpectOutputToContain('super-secret-password')
        ->assertFailed();
});

it('creates the configured application database with the configured charset and collation, and is idempotent', function (): void {
    $database = applicationThrowawayDatabaseName();
    $this->app->instance('env', 'local');
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => $database,
    ]);

    $pdo = applicationMysqlAdminPdo();

    try {
        $this->artisan('db:create-application')
            ->expectsOutputToContain(sprintf('Application database [%s] is ready.', $database))
            ->assertSuccessful();

        $statement = $pdo->prepare('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$database]);

        expect($statement->fetch(PDO::FETCH_NUM))->toBe([
            config('database.connections.mysql.charset'),
            config('database.connections.mysql.collation'),
        ]);

        $this->artisan('db:create-application')->assertSuccessful();
    } finally {
        $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
    }
});
