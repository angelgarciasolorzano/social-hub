<?php

declare(strict_types=1);

namespace App\Modules\Comment\Database\Seeders;

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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
                ->count(random_int(5, 10))
                ->create([
                    'user_id' => $usersIds[array_rand($usersIds)],
                    'commentable_id' => $post->id,
                    'commentable_type' => Post::MORPH_NAME,
                ]);

            $rootComments->each(function (Comment $comment) use ($usersIds): void {
                /** @var Factory<Comment> $factory */
                $factory = Comment::factory();

                $replies = Collection::times(random_int(5, 8), fn (): Comment => $factory->createOne([
                    'user_id' => $usersIds[array_rand($usersIds)],
                    'commentable_id' => $comment->id,
                    'commentable_type' => Comment::MORPH_NAME,
                ]));

                $this->mentionOtherReplyAuthors($replies);
            });
        }));
    }

    /**
     * Make about half of the replies mention the author of another reply in the same thread.
     *
     * @param  Collection<int, Comment>  $replies
     */
    private function mentionOtherReplyAuthors(Collection $replies): void
    {
        $replies->each(function (Comment $reply) use ($replies): void {
            $otherAuthorIds = $replies->pluck('user_id')->unique()->reject($reply->user_id)->values();

            if ($otherAuthorIds->isEmpty() || random_int(0, 1) === 0) {
                return;
            }

            $reply->update(['replied_to_user_id' => $otherAuthorIds->random()]);
        });
    }
}
