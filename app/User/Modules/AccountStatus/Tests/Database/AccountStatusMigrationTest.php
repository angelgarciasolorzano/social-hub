<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Run or roll back only the migration that adds the account status.
 */
function runAccountStatusMigration(string $command): void
{
    Artisan::call($command, [
        '--path' => __DIR__.'/../../../../Database/Migrations/2026_10_23_204636_add_account_status_to_users_table.php',
        '--realpath' => true,
    ]);
}

/**
 * Insert a status change row directly; the model arrives in a later task.
 *
 * @param  array<string, mixed>  $attributes
 */
function insertAccountStatusChange(User $user, array $attributes = []): int
{
    return DB::table('account_status_changes')->insertGetId([
        'user_id' => $user->id,
        'status' => 'suspended',
        'reason' => 'Spam reiterado',
        'changed_by' => null,
        'created_at' => now(),
        ...$attributes,
    ]);
}

it('marks accounts that existed before the migration as active', function (): void {
    $user = createUser();

    try {
        runAccountStatusMigration('migrate:rollback');
        runAccountStatusMigration('migrate');

        expect(User::query()->findOrFail($user->id)->getRawOriginal('status'))->toBe('active');
    } finally {
        Artisan::call('migrate:fresh');
    }
});

it('drops the status column and the history table on rollback', function (): void {
    try {
        runAccountStatusMigration('migrate:rollback');

        expect(Schema::hasColumn('users', 'status'))->toBeFalse()
            ->and(Schema::hasTable('account_status_changes'))->toBeFalse()
            ->and(Schema::hasTable('users'))->toBeTrue();
    } finally {
        Artisan::call('migrate:fresh');
    }
});

it('creates new accounts as active when no status is given', function (): void {
    $user = User::query()->forceCreate([
        'name' => 'Cuenta sin estado',
        'email' => 'sin-estado@example.com',
        'password' => 'password',
    ]);

    expect($user->fresh()?->getRawOriginal('status'))->toBe('active');
});

it('rejects a status change without a reason', function (): void {
    $user = createUser();

    expect(fn (): int => insertAccountStatusChange($user, ['reason' => null]))
        ->toThrow(QueryException::class);
});

it('keeps the status change when the administrator who made it is deleted', function (): void {
    $user = createUser();
    $statusChangeId = insertAccountStatusChange(createUser(), ['changed_by' => $user->id]);

    $user->delete();

    expect(DB::table('account_status_changes')->where('id', $statusChangeId)->value('changed_by'))->toBeNull()
        ->and(DB::table('account_status_changes')->where('id', $statusChangeId)->exists())->toBeTrue();
});

it('deletes the status history together with the account', function (): void {
    $user = createUser();
    insertAccountStatusChange($user);
    insertAccountStatusChange($user, ['status' => 'active']);

    $user->delete();

    expect(DB::table('account_status_changes')->where('user_id', $user->id)->exists())->toBeFalse();
});
