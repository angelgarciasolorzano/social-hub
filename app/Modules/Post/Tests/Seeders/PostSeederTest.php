<?php

declare(strict_types=1);

use App\Modules\Post\Database\Seeders\PostSeeder;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Tests\TestCase;

const POST_SEEDER_SEED = 2024;

/**
 * Seed posts for a few users with a fixed seed and return how many each got.
 *
 * @return list<int>
 */
function seedPostCountsForUsers(TestCase $testCase, int $userCount): array
{
    $users = array_map(createUser(...), range(1, $userCount));

    mt_srand(POST_SEEDER_SEED);
    $testCase->seed(PostSeeder::class);

    return array_map(fn (User $user): int => $user->posts()->count(), $users);
}

it('seeds no more than 50 posts for any user', function (): void {
    expect(seedPostCountsForUsers($this, 8))->each->toBeLessThanOrEqual(50);
});

it('does not give every user the same number of posts', function (): void {
    expect(count(array_unique(seedPostCountsForUsers($this, 8))))->toBeGreaterThan(2);
});

it('can leave a user without posts', function (): void {
    expect(seedPostCountsForUsers($this, 8))->toContain(0);
});

it('does not create posts without an owner', function (): void {
    createUser();

    $this->seed(PostSeeder::class);

    expect(Post::query()->whereNull('user_id')->count())->toBe(0);
});

it('creates no posts when there are no users', function (): void {
    $this->seed(PostSeeder::class);

    expect(Post::query()->count())->toBe(0);
});
