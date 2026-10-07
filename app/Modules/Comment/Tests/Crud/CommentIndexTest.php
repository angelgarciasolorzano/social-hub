<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;

/**
 * Resolve the paginated comments the controller flashes to Inertia, serialized
 * the same way Inertia does (JsonResource::jsonSerialize()).
 *
 * @param  TestResponse<RedirectResponse>  $testResponse
 * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
 */
function flashedComments(TestResponse $testResponse): array
{
    /** @var array<string, mixed> $flash */
    $flash = session()->get(SessionKey::FLASH_DATA, []);

    /** @var ResourceCollection $collection */
    $collection = $flash['comments'];

    /** @var array{data: list<array<string, mixed>>, meta: array<string, mixed>} $payload */
    $payload = json_decode((string) json_encode($collection), true);

    return $payload;
}

it('redirects guests to the login page', function (): void {
    $post = createPost();

    $this->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => $post->id]))
        ->assertRedirect(route('login'));
});

it('lists the comments of a post paginated by cursor in descending id order', function (): void {
    $post = createPost();
    $otherPost = createPost();

    foreach (range(1, 12) as $index) {
        createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);
    }

    createComment(null, ['commentable_id' => $otherPost->id, 'commentable_type' => Post::MORPH_NAME]);

    $testResponse = $this->actingAs(createUser())
        ->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => $post->id]));

    $testResponse->assertRedirect();

    $payload = flashedComments($testResponse);
    $ids = array_column($payload['data'], 'id');
    $sortedIds = $ids;
    rsort($sortedIds);

    expect($payload['data'])->toHaveCount(10)
        ->and($ids)->toBe($sortedIds)
        ->and($payload['meta']['per_page'])->toBe(10)
        ->and($payload['meta']['has_more'])->toBeTrue()
        ->and($payload['meta']['next_cursor'])->not->toBeNull()
        ->and(Comment::query()->where('commentable_id', $post->id)->count())->toBe(12);
});

it('lists the replies of a comment with their author', function (): void {
    $comment = createComment();
    $user = createUser();
    $reply = createComment($user, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);

    $testResponse = $this->actingAs(createUser())
        ->get(route('comments.index', ['commentType' => Comment::MORPH_NAME, 'commentableId' => $comment->id]));

    $payload = flashedComments($testResponse);

    expect($payload['data'])->toHaveCount(1)
        ->and($payload['data'][0]['id'])->toBe($reply->id)
        ->and($payload['data'][0]['user'])->toBe(['id' => $user->id, 'name' => $user->name])
        ->and($payload['meta']['has_more'])->toBeFalse()
        ->and($payload['meta']['next_cursor'])->toBeNull();
});

it('returns an empty page for a post without comments', function (): void {
    $post = createPost();

    $testResponse = $this->actingAs(createUser())
        ->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => $post->id]));

    expect(flashedComments($testResponse)['data'])
        ->toBeEmpty();
});

it('returns 404 when the commentable does not exist', function (): void {
    $this->actingAs(createUser())
        ->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => 999999]))
        ->assertNotFound();
});

it('returns 404 for an unsupported commentable type', function (): void {
    $this->actingAs(createUser())
        ->get(route('comments.index', ['commentType' => 'user', 'commentableId' => 1]))
        ->assertNotFound();
});
