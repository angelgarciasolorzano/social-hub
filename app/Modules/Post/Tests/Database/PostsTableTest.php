<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Run or roll back only the migration that indexes the posts by author and adds their user foreign key.
 */
function runPostsUserForeignKeyMigration(string $command): void
{
    Artisan::call($command, [
        '--path' => __DIR__.'/../../Database/Migrations/2026_11_20_120000_add_user_index_and_foreign_key_to_posts_table.php',
        '--realpath' => true,
    ]);
}

it('indexes the posts by author and ties them to their user with a cascading foreign key', function (): void {
    $indexColumnsByName = array_column(Schema::getIndexes('posts'), 'columns', 'name');
    $foreignKeysByName = array_column(Schema::getForeignKeys('posts'), null, 'name');

    expect($indexColumnsByName['posts_user_id_id_index'] ?? null)->toBe(['user_id', 'id'])
        ->and($foreignKeysByName['posts_user_id_foreign'] ?? null)->toMatchArray([
            'columns' => ['user_id'],
            'foreign_table' => 'users',
            'foreign_columns' => ['id'],
            'on_delete' => 'cascade',
        ]);
});

it('rejects a post whose user does not exist', function (): void {
    $deletedUser = createUser();
    $deletedUser->delete();

    expect(fn (): bool => DB::table('posts')->insert([
        'content' => 'A post without an author',
        'user_id' => $deletedUser->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('deletes the posts of a deleted user', function (): void {
    $user = createUser();
    $post = createPost($user);
    $otherPost = createPost();

    $user->delete();

    expect(Post::query()->whereKey($post->id)->exists())->toBeFalse()
        ->and(Post::query()->whereKey($otherPost->id)->exists())->toBeTrue();
});

it('refuses to migrate while a post belongs to a missing user, without deleting it', function (): void {
    try {
        runPostsUserForeignKeyMigration('migrate:rollback');
        $user = createUser();
        $post = createPost($user);
        $user->delete();

        expect(fn () => runPostsUserForeignKeyMigration('migrate'))
            ->toThrow(RuntimeException::class, '1 post(s) belong to a user that no longer exists')
            ->and(Post::query()->whereKey($post->id)->exists())->toBeTrue()
            ->and(collect(Schema::getForeignKeys('posts'))->pluck('name'))->not->toContain('posts_user_id_foreign');
    } finally {
        Artisan::call('migrate:fresh');
    }
});

it('rolls back and reapplies the migration', function (): void {
    try {
        runPostsUserForeignKeyMigration('migrate:rollback');

        expect(collect(Schema::getForeignKeys('posts'))->pluck('name'))->not->toContain('posts_user_id_foreign')
            ->and(collect(Schema::getIndexes('posts'))->pluck('name'))->not->toContain('posts_user_id_id_index');

        runPostsUserForeignKeyMigration('migrate');

        expect(collect(Schema::getForeignKeys('posts'))->pluck('name'))->toContain('posts_user_id_foreign')
            ->and(collect(Schema::getIndexes('posts'))->pluck('name'))->toContain('posts_user_id_id_index');
    } finally {
        Artisan::call('migrate:fresh');
    }
});
