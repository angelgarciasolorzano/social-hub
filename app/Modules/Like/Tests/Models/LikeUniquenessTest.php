<?php

declare(strict_types=1);

use App\Modules\Like\Models\Like;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

const LIKES_UNIQUE_INDEX = 'likes_user_id_likeable_type_likeable_id_unique';

/**
 * Run or roll back only the migration that adds the unique index.
 */
function runUniqueIndexMigration(string $command): void
{
    Artisan::call($command, [
        '--path' => __DIR__.'/../../Database/Migrations/2026_10_15_221529_add_unique_user_likeable_index_to_likes_table.php',
        '--realpath' => true,
    ]);
}

it('rejects a second like by the same user on the same comment', function (): void {
    $user = createUser();
    $comment = createComment();
    createLike($user, $comment);

    expect(fn (): Like => createLike($user, $comment))->toThrow(UniqueConstraintViolationException::class);
});

it('lets the same user like different comments', function (): void {
    $user = createUser();
    createLike($user, createComment());
    createLike($user, createComment());

    expect($user->likes()->count())->toBe(2);
});

it('lets different users like the same comment', function (): void {
    $comment = createComment();
    createLike(createUser(), $comment);
    createLike(createUser(), $comment);

    expect($comment->likes()->count())->toBe(2);
});

it('lets the same user like a post and a comment sharing the same id', function (): void {
    $user = createUser();
    $post = createPost();
    $comment = createComment(attributes: ['id' => $post->id]);
    createLike($user, $post);
    createLike($user, $comment);

    expect($user->likes()->count())->toBe(2);
});

it('drops the unique index on rollback without dropping the table', function (): void {
    try {
        runUniqueIndexMigration('migrate:rollback');

        expect(Schema::hasTable('likes'))->toBeTrue()
            ->and(Schema::hasIndex('likes', LIKES_UNIQUE_INDEX))->toBeFalse();
    } finally {
        Artisan::call('migrate:fresh');
    }
});
