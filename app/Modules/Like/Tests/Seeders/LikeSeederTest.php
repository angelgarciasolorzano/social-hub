<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Database\Seeders\LikeSeeder;
use App\Modules\Like\Models\Like;
use App\User\Models\User;
use Illuminate\Support\Collection;
use Tests\TestCase;

const LIKE_SEEDER_MAX_LIKES_PER_COMMENT = 8;

/**
 * Create a post with the given number of comments written by other users and run the seeder.
 */
function seedLikesOnComments(TestCase $testCase, int $commentCount = 40): void
{
    createCommentsOn(createPost(), $commentCount);

    $testCase->seed(LikeSeeder::class);
}

/**
 * Number of likes of every seeded comment, keyed by comment id.
 *
 * @return Collection<int|string, mixed>
 */
function likesCountPerComment(): Collection
{
    return Comment::query()->withCount('likes')->pluck('likes_count', 'id');
}

it('seeds no more than 8 likes for any comment', function (): void {
    seedLikesOnComments($this);

    expect(likesCountPerComment())->each->toBeLessThanOrEqual(LIKE_SEEDER_MAX_LIKES_PER_COMMENT);
});

it('does not give every comment the same number of likes', function (): void {
    seedLikesOnComments($this);

    expect(likesCountPerComment()->unique()->count())->toBeGreaterThan(1);
});

it('seeds likes only on comments', function (): void {
    seedLikesOnComments($this);

    expect(Like::query()->where('likeable_type', '!=', Comment::MORPH_NAME)->count())->toBe(0);
});

it('does not fail nor repeat likes when it runs twice', function (): void {
    seedLikesOnComments($this);

    $this->seed(LikeSeeder::class);

    $likesPerUserAndComment = Like::query()->get()->groupBy(
        fn (Like $like): string => $like->user_id.'-'.$like->likeable_id,
    );

    expect($likesPerUserAndComment->map->count())->each->toBe(1);
});

it('gives a comment no more likes than there are users', function (): void {
    createCommentsOn(createPost(), 40, createUser());

    $this->seed(LikeSeeder::class);

    expect(User::query()->count('*'))->toBeLessThan(LIKE_SEEDER_MAX_LIKES_PER_COMMENT)
        ->and(likesCountPerComment())->each->toBeLessThanOrEqual(User::query()->count('*'));
});

it('seeds nothing when there are no comments', function (): void {
    $this->seed(LikeSeeder::class);

    expect(Like::query()->count())->toBe(0);
});
