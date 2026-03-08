<?php

namespace App\Comment\Seeders;

use App\Comment\Models\Comment;
use App\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usersIds = User::query()->pluck('id')->all();

        Post::all()->each((function (Post $post) use ($usersIds): void {
            /** @var Factory<Comment> $factory */
            $factory = Comment::factory();

            $rootComments = $factory
                ->count(rand(5, 10))
                ->create([
                    'user_id' => $usersIds[array_rand($usersIds)],
                    'commentable_id' => $post->id,
                    'commentable_type' => Post::MORPH_NAME,
                ]);

            $rootComments->each(function (Comment $comment) use ($usersIds): void {
                /** @var Factory<Comment> $factory */
                $factory = Comment::factory();

                $factory
                    ->count(rand(5, 8))
                    ->create([
                        'user_id' => $usersIds[array_rand($usersIds)],
                        'commentable_id' => $comment->id,
                        'commentable_type' => Comment::MORPH_NAME,
                    ]);
            });
        }));
    }
}
