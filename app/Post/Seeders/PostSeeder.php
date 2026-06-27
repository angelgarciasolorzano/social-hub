<?php

declare(strict_types=1);

namespace App\Post\Seeders;

use App\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::all()->each(function (User $user): void {
            /** @var Factory<Post> $postFactory */
            $postFactory = Post::factory();

            $postFactory
                ->count(random_int(5, 20))
                ->for($user)
                ->create();
        });
    }
}
