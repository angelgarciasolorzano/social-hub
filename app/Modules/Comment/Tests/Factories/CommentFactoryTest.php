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

    $comment = CommentFactory::new()->repliedTo($mentionedUser)->createOne();

    expect($comment->replied_to_user_id)->toBe($mentionedUser->id);
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

it('is neither edited nor deleted by default', function (): void {
    $comment = createComment()->fresh();

    expect($comment?->edited_at)->toBeNull()
        ->and($comment?->deleted_at)->toBeNull();
});

it('marks the comment as edited after it was written with the edited state', function (): void {
    $comment = CommentFactory::new()->edited()->createOne()->fresh();
    $createdTimestamp = $comment?->created_at->timestamp ?? 0;

    expect($comment?->edited_at)->not->toBeNull()
        ->and($comment?->edited_at?->timestamp)->toBeGreaterThan($createdTimestamp)
        ->and($comment?->deleted_at)->toBeNull();
});

it('marks the comment as deleted with the trashed state', function (): void {
    $comment = CommentFactory::new()->trashed()->createOne();

    expect(Comment::query()->whereKey($comment->id)->exists())->toBeFalse()
        ->and(Comment::withTrashed()->find($comment->id)?->trashed())->toBeTrue();
});
