<?php

declare(strict_types=1);

namespace App\Modules\Like\Database\Seeders;

use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Support\Seeding\SkewedRandom;
use App\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class LikeSeeder extends Seeder
{
    private const int MAX_LIKES_PER_COMMENT = 8;

    private const float LIKES_SKEW = 3.0;

    private const int COMMENTS_PER_CHUNK = 500;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var list<int> $usersIds */
        $usersIds = User::query()->pluck('id')->all();

        if ($usersIds === []) {
            return;
        }

        Comment::query()->chunkById(self::COMMENTS_PER_CHUNK, function (Collection $comments) use ($usersIds): void {
            $likeRows = [];

            foreach ($comments as $comment) {
                $likeCount = min(
                    SkewedRandom::count(0, self::MAX_LIKES_PER_COMMENT, self::LIKES_SKEW),
                    \count($usersIds),
                );

                if ($likeCount === 0) {
                    continue;
                }

                foreach (Arr::random($usersIds, $likeCount) as $userId) {
                    $likeRows[] = [
                        'user_id' => $userId,
                        'likeable_type' => Comment::MORPH_NAME,
                        'likeable_id' => $comment->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            Like::query()->insertOrIgnore($likeRows);
        });
    }
}
