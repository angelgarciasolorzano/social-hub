<?php

declare(strict_types=1);

namespace App\Modules\Post\Database\Seeders;

use App\Modules\Post\Models\Post;
use App\Support\Seeding\SkewedRandom;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    private const int MAX_POSTS_PER_USER = 50;

    private const float POSTS_SKEW = 3.0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::all()->each(function (User $user): void {
            /** @var Factory<Post> $postFactory */
            $postFactory = Post::factory();

            $postFactory
                ->count(SkewedRandom::count(0, self::MAX_POSTS_PER_USER, self::POSTS_SKEW))
                ->for($user)
                ->create();
        });
    }
}
