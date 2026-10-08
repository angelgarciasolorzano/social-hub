<?php

declare(strict_types=1);

/**
 * Open an admin PDO connection (no database selected) with the testing MySQL credentials.
 *
 * It is separate from the application connection on purpose: CREATE/DROP DATABASE
 * would implicitly commit the transaction RefreshDatabase wraps each test in.
 */
function testingMysqlAdminPdo(): PDO
{
    /** @var array<string, mixed> $connection */
    $connection = config('database.connections.mysql');

    return new PDO(
        sprintf('mysql:host=%s;port=%s', $connection['host'], $connection['port']),
        (string) $connection['username'],
        (string) $connection['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

/**
 * Build a unique, valid name for a throwaway database.
 */
function throwawayDatabaseName(): string
{
    return 'social_hub_cmd_'.bin2hex(random_bytes(4));
}

it('refuses to run outside the testing environment', function (): void {
    $this->app->instance('env', 'local');

    $this->artisan('db:create-testing')
        ->expectsOutputToContain('must run in the testing environment')
        ->assertFailed();
});

it('fails when the configured database name is not a string', function (): void {
    config(['database.connections.mysql.database' => null]);

    $this->artisan('db:create-testing')
        ->expectsOutputToContain('must contain string values')
        ->assertFailed();
});

it('rejects invalid identifiers before touching MySQL', function (string $key, string $value): void {
    config(['database.connections.mysql.'.$key => $value]);

    $this->artisan('db:create-testing')
        ->expectsOutputToContain('contains an invalid identifier')
        ->assertFailed();
})->with([
    'database with a statement separator' => ['database', 'testing`; DROP DATABASE mysql; --'],
    'database with a space' => ['database', 'my database'],
    'empty database' => ['database', ''],
    'charset with a quote' => ['charset', "utf8mb4'"],
    'collation with a semicolon' => ['collation', 'utf8mb4_unicode_ci;'],
]);

it('fails with a generic message and never leaks the credentials when MySQL is unreachable', function (): void {
    config([
        'database.connections.mysql.unix_socket' => '/nonexistent/mysql.sock',
        'database.connections.mysql.password' => 'super-secret-password',
    ]);

    $this->artisan('db:create-testing')
        ->expectsOutputToContain('could not be created')
        ->doesntExpectOutputToContain('super-secret-password')
        ->assertFailed();
});

it('creates the testing database with the configured charset and collation, and is idempotent', function (): void {
    $database = throwawayDatabaseName();
    config(['database.connections.mysql.database' => $database]);

    $pdo = testingMysqlAdminPdo();

    try {
        $this->artisan('db:create-testing')
            ->expectsOutputToContain(sprintf('Testing database [%s] is ready.', $database))
            ->assertSuccessful();

        $statement = $pdo->prepare('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$database]);

        expect($statement->fetch(PDO::FETCH_NUM))->toBe([
            config('database.connections.mysql.charset'),
            config('database.connections.mysql.collation'),
        ]);

        $this->artisan('db:create-testing')->assertSuccessful();
    } finally {
        $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
    }
});
