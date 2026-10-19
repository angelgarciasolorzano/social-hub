<?php

declare(strict_types=1);

use App\Modules\Comment\Database\Seeders\CommentSeeder;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Collection;
use Tests\TestCase;

const COMMENT_SEEDER_SEED = 77;

/**
 * Create a few posts (each with its own author) and seed their comment threads with a fixed seed.
 */
function seedCommentThreads(TestCase $testCase, int $postCount = 8): void
{
    foreach (range(1, $postCount) as $postNumber) {
        createPost();
    }

    mt_srand(COMMENT_SEEDER_SEED);
    $testCase->seed(CommentSeeder::class);
}

/**
 * @return Collection<int, Comment>
 */
function seededRootComments(): Collection
{
    return Comment::query()->where('commentable_type', Post::MORPH_NAME)->get();
}

/**
 * @return Collection<int, Comment>
 */
function seededReplies(): Collection
{
    return Comment::query()->where('commentable_type', Comment::MORPH_NAME)->get();
}

/**
 * Highest number of distinct authors found in any group of comments (one group per post or per thread).
 *
 * @param  Collection<int, Comment>  $comments
 */
function maxDistinctAuthorsPerGroup(Collection $comments): int
{
    return $comments->groupBy('commentable_id')->reduce(
        fn (int $carry, Collection $group): int => max($carry, $group->pluck('user_id')->unique()->count()),
        0,
    );
}

it('seeds no more than 10 root comments for any post', function (): void {
    seedCommentThreads($this);

    expect(Post::all()->map(fn (Post $post): int => $post->comments()->count()))->each->toBeLessThanOrEqual(10);
});

it('does not give every post the same number of root comments', function (): void {
    seedCommentThreads($this);

    $rootCommentCounts = Post::all()->map(fn (Post $post): int => $post->comments()->count());

    expect($rootCommentCounts->unique()->count())->toBeGreaterThan(1);
});

it('seeds no more than 6 replies for any root comment', function (): void {
    seedCommentThreads($this);

    expect(seededRootComments()->map(fn (Comment $comment): int => $comment->repliesCount()))->each->toBeLessThanOrEqual(6);
});

it('does not give every root comment the same number of replies', function (): void {
    seedCommentThreads($this);

    $replyCounts = seededRootComments()->map(fn (Comment $comment): int => $comment->repliesCount());

    expect($replyCounts->unique()->count())->toBeGreaterThan(1);
});

it('can leave a root comment without replies', function (): void {
    seedCommentThreads($this);

    expect(seededRootComments()->map(fn (Comment $comment): int => $comment->repliesCount()))->toContain(0);
});

it('does not write every root comment of a post with the same author', function (): void {
    seedCommentThreads($this);

    expect(maxDistinctAuthorsPerGroup(seededRootComments()))->toBeGreaterThan(1);
});

it('does not write every reply of a thread with the same author', function (): void {
    seedCommentThreads($this);

    expect(maxDistinctAuthorsPerGroup(seededReplies()))->toBeGreaterThan(1);
});

it('attributes every seeded comment to an existing user', function (): void {
    seedCommentThreads($this, 2);

    expect(Comment::query()->whereNull('user_id')->count())->toBe(0)
        ->and(Comment::query()->count())->toBeGreaterThan(0);
});

it('creates no comments when there are no posts', function (): void {
    createUser();

    $this->seed(CommentSeeder::class);

    expect(Post::query()->count())->toBe(0)
        ->and(Comment::query()->count())->toBe(0);
});

it('seeds a thread without mentions when the only user wrote everything', function (): void {
    createPost(createUser());
    mt_srand(COMMENT_SEEDER_SEED);

    $this->seed(CommentSeeder::class);

    expect(Comment::query()->whereNotNull('replied_to_user_id')->count())->toBe(0);
});

it('makes some replies mention the author of another reply in the same thread', function (): void {
    seedCommentThreads($this);

    $mentioningReplies = seededReplies()->whereNotNull('replied_to_user_id');

    expect($mentioningReplies)->not->toBeEmpty();

    foreach ($mentioningReplies as $mentioningReply) {
        $threadAuthorIds = Comment::query()
            ->where('commentable_type', Comment::MORPH_NAME)
            ->where('commentable_id', $mentioningReply->commentable_id)
            ->whereKeyNot($mentioningReply->id)
            ->pluck('user_id')
            ->all();

        expect($threadAuthorIds)->toContain($mentioningReply->replied_to_user_id)
            ->and($mentioningReply->replied_to_user_id)->not->toBe($mentioningReply->user_id);
    }
});

it('never mentions anyone on root comments', function (): void {
    seedCommentThreads($this);

    expect(seededRootComments()->whereNotNull('replied_to_user_id'))->toBeEmpty();
});

it('keeps threads to a single level', function (): void {
    seedCommentThreads($this);

    $replyIds = seededReplies()->pluck('id')->all();

    expect(Comment::query()->where('commentable_type', Comment::MORPH_NAME)->whereIn('commentable_id', $replyIds)->count())->toBe(0);
});

it('marks some comments as edited after they were written', function (): void {
    seedCommentThreads($this, 20);

    $editedComments = Comment::query()->whereNotNull('edited_at')->get();

    expect($editedComments)->not->toBeEmpty();

    foreach ($editedComments as $editedComment) {
        expect($editedComment->edited_at?->timestamp)->toBeGreaterThan($editedComment->created_at->timestamp ?? 0);
    }
});

it('does not mark every comment as edited', function (): void {
    seedCommentThreads($this, 20);

    expect(Comment::query()->whereNull('edited_at')->count())->toBeGreaterThan(0);
});

it('leaves some root comments with replies as deleted tombstones', function (): void {
    seedCommentThreads($this, 60);

    expect(Comment::onlyTrashed()->count())->toBeGreaterThan(0);
});

it('only deletes root comments that have replies', function (): void {
    seedCommentThreads($this, 60);

    foreach (Comment::onlyTrashed()->get() as $deletedComment) {
        expect($deletedComment->commentable_type)->toBe(CommentType::POST)
            ->and(Comment::query()->where('commentable_type', Comment::MORPH_NAME)->where('commentable_id', $deletedComment->id)->exists())->toBeTrue();
    }
});

it('never deletes a reply', function (): void {
    seedCommentThreads($this, 60);

    expect(Comment::onlyTrashed()->where('commentable_type', Comment::MORPH_NAME)->count())->toBe(0);
});
