<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Props\CommentThreadProps;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Load the comments of a post, or the replies of a comment, the way the dialog does and return the serialized page plus the number of queries used.
 *
 * @return array{payload: array{data: list<array<string, mixed>>}, queries: int}
 */
function listCommentsCountingQueries(TestCase $testCase, Post|Comment $thread): array
{
    $prop = $thread instanceof Comment ? 'replies' : 'comments';
    $parameter = $thread instanceof Comment ? CommentThreadProps::REPLIES_PARAMETER : CommentThreadProps::COMMENTS_PARAMETER;

    $user = createUser();

    $testCase->actingAs($user);

    $headers = inertiaPartialHeaders($testCase, 'home/Home', $prop);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testResponse = $testCase->get(route('home', [$parameter => $thread->id]), $headers);

    $queries = count(DB::connection()->getQueryLog());
    DB::disableQueryLog();

    /** @var array{data: list<array<string, mixed>>} $payload */
    $payload = $testResponse->assertOk()->json('props.'.$prop);

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
        createLike(createUser(), $comment);
    }

    $small = listCommentsCountingQueries($this, $smallPost);
    $large = listCommentsCountingQueries($this, $largePost);

    expect($large['payload']['data'])->toHaveCount(10)
        ->and($large['queries'])->toBe($small['queries']);
});

it('uses the same number of queries for replies with mentions regardless of how many are listed', function (): void {
    $smallThreadComment = createComment();
    createCommentOn($smallThreadComment, attributes: ['replied_to_user_id' => createUser()->id]);

    $largeThreadComment = createComment();
    foreach (range(1, 10) as $attempt) {
        createCommentOn($largeThreadComment, attributes: ['replied_to_user_id' => createUser()->id]);
    }

    $small = listCommentsCountingQueries($this, $smallThreadComment);
    $large = listCommentsCountingQueries($this, $largeThreadComment);

    expect($large['payload']['data'])->toHaveCount(10)
        ->and(array_filter(array_column($large['payload']['data'], 'repliedTo'), is_array(...)))->toHaveCount(10)
        ->and($large['queries'])->toBe($small['queries']);
});
