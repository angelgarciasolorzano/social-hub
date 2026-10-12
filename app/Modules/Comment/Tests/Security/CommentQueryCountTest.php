<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Props\CommentThreadProps;
use App\Modules\Comment\Resources\CommentResource;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Load the comments of a post the way the dialog does and return the serialized page plus the number of queries used.
 *
 * @return array{payload: array{data: list<array<string, mixed>>}, queries: int}
 */
function listCommentsCountingQueries(TestCase $testCase, Post $post): array
{
    $user = createUser();

    /** @var array{version: string} $page */
    $page = $testCase->actingAs($user)->get(route('home'))->inertiaPage();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testResponse = $testCase->actingAs($user)->get(
        route('home', [CommentThreadProps::COMMENTS_PARAMETER => $post->id]),
        [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $page['version'],
            'X-Inertia-Partial-Component' => 'home/Home',
            'X-Inertia-Partial-Data' => 'comments',
        ],
    );

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    /** @var array{data: list<array<string, mixed>>} $payload */
    $payload = $testResponse->assertOk()->json('props.comments');

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
