<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Resources\CommentResource;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * List the comments of a post and return the serialized page plus the number of queries used.
 *
 * @return array{payload: array{data: list<array<string, mixed>>}, queries: int}
 */
function listCommentsCountingQueries(TestCase $testCase, Post $post): array
{
    $user = createUser();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testCase->actingAs($user)
        ->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => $post->id]));

    /** @var array<string, mixed> $flash */
    $flash = session()->get(SessionKey::FLASH_DATA, []);

    /** @var array{data: list<array<string, mixed>>} $payload */
    $payload = json_decode((string) json_encode($flash['comments']), true);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return ['payload' => $payload, 'queries' => $queries];
}

it('reports the exact replies info of each comment in the listing', function (): void {
    $post = createPost();
    $comment = createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);
    $withOneReply = createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);
    $withThreeReplies = createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);

    createComment(null, ['commentable_id' => $withOneReply->id, 'commentable_type' => Comment::MORPH_NAME]);
    foreach (range(1, 3) as $attempt) {
        createComment(null, ['commentable_id' => $withThreeReplies->id, 'commentable_type' => Comment::MORPH_NAME]);
    }

    $listing = listCommentsCountingQueries($this, $post);
    $repliesInfo = array_column($listing['payload']['data'], 'repliesInfo', 'id');

    expect($repliesInfo[$comment->id])->toBe(['hasReplies' => false, 'repliesCount' => 0])
        ->and($repliesInfo[$withOneReply->id])->toBe(['hasReplies' => true, 'repliesCount' => 1])
        ->and($repliesInfo[$withThreeReplies->id])->toBe(['hasReplies' => true, 'repliesCount' => 3]);
});

it('uses the same number of queries regardless of how many comments or replies are listed', function (): void {
    $smallPost = createPost();
    createComment(null, ['commentable_id' => $smallPost->id, 'commentable_type' => Post::MORPH_NAME]);

    $largePost = createPost();
    foreach (range(1, 10) as $attempt) {
        $comment = createComment(null, ['commentable_id' => $largePost->id, 'commentable_type' => Post::MORPH_NAME]);
        createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);
    }

    $small = listCommentsCountingQueries($this, $smallPost);
    $large = listCommentsCountingQueries($this, $largePost);

    expect($large['payload']['data'])->toHaveCount(10)
        ->and($large['queries'])->toBe($small['queries']);
});

it('still counts replies when the resource is built without the preloaded count', function (): void {
    $comment = createComment();
    createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);

    $payload = new CommentResource($comment)->toArray(request());

    expect($payload['repliesInfo'])->toBe(['hasReplies' => true, 'repliesCount' => 1]);
});
