<?php

declare(strict_types=1);

use App\Like\Models\Like;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;

it('belongs to the user who wrote it', function (): void {
    $user = createUser();
    $comment = createComment($user);

    expect($comment->user->is($user))->toBeTrue()
        ->and($user->comments()->pluck('id')->all())->toBe([$comment->id]);
});

it('belongs to the post or comment it was written on', function (): void {
    $post = createPost();
    $postComment = createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);
    $comment = createComment(null, ['commentable_id' => $postComment->id, 'commentable_type' => Comment::MORPH_NAME]);

    expect($postComment->commentable)->toBeInstanceOf(Post::class)
        ->and($postComment->commentable->getKey())->toBe($post->id)
        ->and($comment->commentable)->toBeInstanceOf(Comment::class)
        ->and($comment->commentable->getKey())->toBe($postComment->id);
});

it('loads its replies with their author', function (): void {
    $comment = createComment();
    $user = createUser();
    $reply = createComment($user, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);
    createComment(null, ['commentable_id' => createComment()->id, 'commentable_type' => Comment::MORPH_NAME]);

    $replies = $comment->comments()->get();

    expect($replies->pluck('id')->all())->toBe([$reply->id])
        ->and($replies->first()?->relationLoaded('user'))->toBeTrue()
        ->and($replies->first()?->user->is($user))->toBeTrue();
});

it('reports whether it has replies and how many', function (): void {
    $comment = createComment();

    expect($comment->hasReplies())->toBeFalse()
        ->and($comment->repliesCount())->toBe(0);

    createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);
    createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);

    expect($comment->hasReplies())->toBeTrue()
        ->and($comment->repliesCount())->toBe(2);
});

it('owns the likes attached to it', function (): void {
    $user = createUser();
    $comment = createComment();

    $like = new Like;
    $like->forceFill(['user_id' => $user->id]);
    $comment->likes()->save($like);

    expect($comment->likes()->pluck('id')->all())->toBe([$like->id]);
});

it('casts the commentable type to the CommentType enum', function (): void {
    $postComment = createComment();
    $comment = createComment(null, ['commentable_id' => $postComment->id, 'commentable_type' => Comment::MORPH_NAME]);

    expect($postComment->refresh()->commentable_type)->toBe(CommentType::POST)
        ->and($comment->refresh()->commentable_type)->toBe(CommentType::COMMENT);
});

it('is registered in the morph map under the comment key', function (): void {
    expect(Comment::MORPH_NAME)->toBe('comment')
        ->and(Relation::getMorphedModel('comment'))->toBe(Comment::class)
        ->and((new Comment)->getMorphClass())->toBe('comment');
});
