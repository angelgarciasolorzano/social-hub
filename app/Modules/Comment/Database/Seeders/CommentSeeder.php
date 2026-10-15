<?php

declare(strict_types=1);

namespace App\Modules\Comment\Database\Seeders;

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\Support\Seeding\SkewedRandom;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CommentSeeder extends Seeder
{
    private const int MAX_COMMENTS_PER_POST = 10;

    private const float COMMENTS_SKEW = 2.0;

    private const int MAX_REPLIES_PER_COMMENT = 6;

    private const float REPLIES_SKEW = 2.0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var list<int> $usersIds */
        $usersIds = User::query()->pluck('id')->all();

        Post::all()->each(function (Post $post) use ($usersIds): void {
            $rootComments = $this->createComments(
                SkewedRandom::count(0, self::MAX_COMMENTS_PER_POST, self::COMMENTS_SKEW),
                $usersIds,
                $post->id,
                Post::MORPH_NAME,
            );

            $rootComments->each(function (Comment $comment) use ($usersIds): void {
                $replies = $this->createComments(
                    SkewedRandom::count(0, self::MAX_REPLIES_PER_COMMENT, self::REPLIES_SKEW),
                    $usersIds,
                    $comment->id,
                    Comment::MORPH_NAME,
                );

                $this->mentionOtherReplyAuthors($replies);
            });
        });
    }

    /**
     * Create the given number of comments on one commentable, each written by
     * its own randomly chosen user.
     *
     * @param  list<int>  $usersIds
     * @return Collection<int, Comment>
     */
    private function createComments(int $count, array $usersIds, int $commentableId, string $commentableType): Collection
    {
        /** @var Factory<Comment> $factory */
        $factory = Comment::factory();

        return Collection::times($count, fn (): Comment => $factory->createOne([
            'user_id' => $usersIds[array_rand($usersIds)],
            'commentable_id' => $commentableId,
            'commentable_type' => $commentableType,
        ]));
    }

    /**
     * Make about half of the replies mention the author of another reply in the same thread.
     *
     * @param  Collection<int, Comment>  $replies
     */
    private function mentionOtherReplyAuthors(Collection $replies): void
    {
        $replies->each(function (Comment $comment) use ($replies): void {
            $otherAuthorIds = $replies->pluck('user_id')->unique()->reject($comment->user_id)->values();

            if ($otherAuthorIds->isEmpty() || random_int(0, 1) === 0) {
                return;
            }

            $comment->update(['replied_to_user_id' => $otherAuthorIds->random()]);
        });
    }
}
