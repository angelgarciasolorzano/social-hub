<?php

declare(strict_types=1);

use App\Modules\Comment\Database\Factories\CommentFactory;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;

it('creates a valid comment on a new post by default', function (): void {
    $comment = createComment();

    expect($comment->content)->not->toBeEmpty()
        ->and(User::query()->whereKey($comment->user_id)->exists())->toBeTrue()
        ->and($comment->commentable_type)->toBe(CommentType::POST)
        ->and(Post::query()->whereKey($comment->commentable_id)->exists())->toBeTrue();
});

it('does not mention anyone by default', function (): void {
    expect(createComment()->replied_to_user_id)->toBeNull();
});

it('mentions the given user with the repliedTo state', function (): void {
    $mentionedUser = createUser();

    $reply = CommentFactory::new()->repliedTo($mentionedUser)->createOne();

    expect($reply->replied_to_user_id)->toBe($mentionedUser->id);
});

it('assigns the comment to the given author', function (): void {
    $user = createUser();

    $comment = createComment($user);

    expect($comment->user_id)->toBe($user->id);
});

it('lets explicit attributes override the defaults', function (): void {
    $comment = createComment();

    $reply = createComment(null, [
        'content' => 'Contenido explícito',
        'commentable_id' => $comment->id,
        'commentable_type' => Comment::MORPH_NAME,
    ]);

    expect($reply->content)->toBe('Contenido explícito')
        ->and($reply->commentable_id)->toBe($comment->id)
        ->and($reply->commentable_type)->toBe(CommentType::COMMENT);
});
