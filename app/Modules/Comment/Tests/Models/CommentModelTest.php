<?php

declare(strict_types=1);

use App\Like\Models\Like;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

it('belongs to the user who wrote it', function (): void {
    $user = createUser();
    $comment = createComment($user);

    expect($comment->user->is($user))->toBeTrue()
        ->and($user->comments()->pluck('id')->all())->toBe([$comment->id]);
});

it('belongs to the user it replies to', function (): void {
    $mentionedUser = createUser();
    $replyComment = createCommentOn(createComment(), attributes: ['replied_to_user_id' => $mentionedUser->id]);

    expect($replyComment->repliedToUser)->not->toBeNull()
        ->and($replyComment->repliedToUser?->is($mentionedUser))->toBeTrue();
});

it('has no replied to user when it does not mention anyone', function (): void {
    expect(createComment()->repliedToUser)->toBeNull();
});

it('keeps the comment and clears the mention when the mentioned user is deleted', function (): void {
    $mentionedUser = createUser();
    $replyComment = createCommentOn(createComment(), attributes: ['replied_to_user_id' => $mentionedUser->id]);

    User::query()->whereKey($mentionedUser->id)->delete();

    $freshReply = $replyComment->fresh();

    expect($freshReply)->not->toBeNull()
        ->and($freshReply?->replied_to_user_id)->toBeNull();
});

it('belongs to the post or comment it was written on', function (): void {
    $post = createPost();
    $postComment = createCommentOn($post);
    $comment = createCommentOn($postComment);

    expect($postComment->commentable)->toBeInstanceOf(Post::class)
        ->and($postComment->commentable->getKey())->toBe($post->id)
        ->and($comment->commentable)->toBeInstanceOf(Comment::class)
        ->and($comment->commentable->getKey())->toBe($postComment->id);
});

it('loads its replies with their author', function (): void {
    $comment = createComment();
    $user = createUser();
    $reply = createCommentOn($comment, $user);
    createCommentOn(createComment());

    $replies = $comment->comments()->get();

    expect($replies->pluck('id')->all())->toBe([$reply->id])
        ->and($replies->first()?->relationLoaded('user'))->toBeTrue()
        ->and($replies->first()?->user->is($user))->toBeTrue();
});

it('reports whether it has replies and how many', function (): void {
    $comment = createComment();

    expect($comment->hasReplies())->toBeFalse()
        ->and($comment->repliesCount())->toBe(0);

    createCommentOn($comment);
    createCommentOn($comment);

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
    $comment = createCommentOn($postComment);

    expect($postComment->refresh()->commentable_type)->toBe(CommentType::POST)
        ->and($comment->refresh()->commentable_type)->toBe(CommentType::COMMENT);
});

it('is registered in the morph map under the comment key', function (): void {
    expect(Comment::MORPH_NAME)->toBe('comment')
        ->and(Relation::getMorphedModel('comment'))->toBe(Comment::class)
        ->and((new Comment)->getMorphClass())->toBe('comment');
});
