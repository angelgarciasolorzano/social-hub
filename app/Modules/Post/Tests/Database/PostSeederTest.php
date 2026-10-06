<?php

declare(strict_types=1);

use App\Modules\Post\Database\Seeders\PostSeeder;
use App\Modules\Post\Models\Post;

it('seeds between 5 and 20 posts for each user', function (): void {
    $users = [createUser(), createUser()];

    $this->seed(PostSeeder::class);

    foreach ($users as $user) {
        expect($user->posts()->count())->toBeBetween(5, 20);
    }
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
