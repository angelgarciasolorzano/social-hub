<?php

declare(strict_types=1);

use App\Modules\Comment\Props\CommentThreadProps;
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

    $testCase->actingAs($user);

    $headers = inertiaPartialHeaders($testCase, 'home/Home', 'comments');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testResponse = $testCase->get(route('home', [CommentThreadProps::COMMENTS_PARAMETER => $post->id]), $headers);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    /** @var array{data: list<array<string, mixed>>} $payload */
    $payload = $testResponse->assertOk()->json('props.comments');

    return ['payload' => $payload, 'queries' => $queries];
}

it('reports the exact replies info of each comment in the listing', function (): void {
    $post = createPost();
    $comment = createCommentOn($post);
    $withOneReply = createCommentOn($post);
    $withThreeReplies = createCommentOn($post);

    createCommentOn($withOneReply);
    createCommentsOn($withThreeReplies, 3);

    $listing = listCommentsCountingQueries($this, $post);
    $repliesInfo = array_column($listing['payload']['data'], 'repliesInfo', 'id');

    expect($repliesInfo[$comment->id])->toBe(['hasReplies' => false, 'repliesCount' => 0])
        ->and($repliesInfo[$withOneReply->id])->toBe(['hasReplies' => true, 'repliesCount' => 1])
        ->and($repliesInfo[$withThreeReplies->id])->toBe(['hasReplies' => true, 'repliesCount' => 3]);
});

it('uses the same number of queries regardless of how many comments or replies are listed', function (): void {
    $smallPost = createPost();
    createCommentOn($smallPost);

    $largePost = createPost();
    foreach (range(1, 10) as $attempt) {
        $comment = createCommentOn($largePost);
        createCommentOn($comment);
    }

    $small = listCommentsCountingQueries($this, $smallPost);
    $large = listCommentsCountingQueries($this, $largePost);

    expect($large['payload']['data'])->toHaveCount(10)
        ->and($large['queries'])->toBe($small['queries']);
});
