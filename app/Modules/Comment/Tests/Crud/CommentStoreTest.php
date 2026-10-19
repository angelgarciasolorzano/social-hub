<?php

declare(strict_types=1);

use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;

it('redirects guests to the login page', function (): void {
    $post = createPost();

    $this->post(route('comments.store'), [
        'content' => 'Un comentario',
        'commentable_type' => Post::MORPH_NAME,
        'commentable_id' => $post->id,
    ])->assertRedirect(route('login'));

    expect(Comment::query()->count())->toBe(0);
});

it('comments on a post as the authenticated user and flashes a success message', function (): void {
    $user = createUser();
    $post = createPost();

    $testResponse = $this->actingAs($user)->post(route('comments.store'), [
        'content' => 'Un comentario',
        'commentable_type' => Post::MORPH_NAME,
        'commentable_id' => $post->id,
    ]);

    $testResponse->assertRedirect();
    $testResponse->assertInertiaFlash('type', 'success');
    $testResponse->assertInertiaFlash('message', 'Comentario publicado correctamente');

    $comment = Comment::query()->sole();

    expect($comment->user_id)->toBe($user->id)
        ->and($comment->content)->toBe('Un comentario')
        ->and($comment->commentable_id)->toBe($post->id)
        ->and($comment->commentable_type->value)->toBe(Post::MORPH_NAME);
});

it('replies to another comment', function (): void {
    $user = createUser();
    $comment = createComment();

    $this->actingAs($user)
        ->post(route('comments.store'), [
            'content' => 'Una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $comment->id,
        ])
        ->assertRedirect();

    $reply = Comment::query()->whereKeyNot($comment->id)->sole();

    expect($reply->commentable_id)->toBe($comment->id)
        ->and($reply->commentable_type->value)->toBe(Comment::MORPH_NAME)
        ->and($reply->user_id)->toBe($user->id)
        ->and($comment->comments()->count())->toBe(1);
});

it('does not mention anyone when commenting on a post or replying to a root comment', function (): void {
    $user = createUser();
    $comment = createComment();

    $this->actingAs($user)->post(route('comments.store'), [
        'content' => 'Un comentario',
        'commentable_type' => Post::MORPH_NAME,
        'commentable_id' => $comment->commentable_id,
    ]);
    $this->actingAs($user)->post(route('comments.store'), [
        'content' => 'Una respuesta',
        'commentable_type' => Comment::MORPH_NAME,
        'commentable_id' => $comment->id,
    ]);

    expect(Comment::query()->whereKeyNot($comment->id)->pluck('replied_to_user_id')->all())->toBe([null, null]);
});

it('attaches a reply to a reply to the parent comment and mentions its author', function (): void {
    $replierUser = createUser();
    $replyAuthorUser = createUser();
    $comment = createComment();
    $reply = createCommentOn($comment, $replyAuthorUser);

    $this->actingAs($replierUser)
        ->post(route('comments.store'), [
            'content' => 'Una respuesta a una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $reply->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $newReply = Comment::query()->whereNotIn('id', [$comment->id, $reply->id])->sole();

    expect($newReply->commentable_id)->toBe($comment->id)
        ->and($newReply->commentable_type)->toBe(CommentType::COMMENT)
        ->and($newReply->user_id)->toBe($replierUser->id)
        ->and($newReply->replied_to_user_id)->toBe($replyAuthorUser->id)
        ->and($reply->repliesCount())->toBe(0)
        ->and($comment->repliesCount())->toBe(2);
});

it('returns 404 and creates nothing when the parent of the replied comment no longer exists', function (): void {
    $comment = createComment();
    $reply = createCommentOn($comment);
    $comment->forceDelete();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Una respuesta a una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $reply->id,
        ])
        ->assertNotFound();

    expect(Comment::query()->count())->toBe(1);
});

it('requires the content of the comment', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => '',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasErrors(['content' => 'El comentario es obligatorio']);

    expect(Comment::query()->count())->toBe(0);
});

it('rejects content longer than 1000 characters', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => str_repeat('a', 1001),
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasErrors('content');

    expect(Comment::query()->count())->toBe(0);
});

it('accepts content of exactly 1000 characters', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => str_repeat('a', 1000),
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->count())->toBe(1);
});

it('rejects an unsupported commentable type', function (): void {
    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => 'user',
            'commentable_id' => 1,
        ])
        ->assertSessionHasErrors('commentable_type');

    expect(Comment::query()->count())->toBe(0);
});

it('rejects a non-integer commentable id', function (): void {
    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => 'abc',
        ])
        ->assertSessionHasErrors('commentable_id');

    expect(Comment::query()->count())->toBe(0);
});

it('returns 404 and creates nothing when the commentable does not exist', function (string $commentableType): void {
    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => $commentableType,
            'commentable_id' => 999999,
        ])
        ->assertNotFound();

    expect(Comment::query()->count())->toBe(0);
})->with([Post::MORPH_NAME, Comment::MORPH_NAME]);

it('returns 404 and creates nothing when commenting directly on a deleted comment', function (): void {
    $deletedComment = createComment(null, ['deleted_at' => now()]);
    createCommentOn($deletedComment);

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $deletedComment->id,
        ])
        ->assertNotFound();

    expect(Comment::query()->count())->toBe(1);
});

it('replies to a reply of a deleted comment keeping the thread under the deleted root', function (): void {
    $deletedComment = createComment(null, ['deleted_at' => now()]);
    $replyAuthor = createUser();
    $comment = createCommentOn($deletedComment, $replyAuthor);
    $user = createUser();

    $this->actingAs($user)
        ->post(route('comments.store'), [
            'content' => 'Otra respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $comment->id,
        ])
        ->assertRedirect();

    $newReply = Comment::query()->whereKeyNot($comment->id)->sole();

    expect($newReply->commentable_id)->toBe($deletedComment->id)
        ->and($newReply->user_id)->toBe($user->id)
        ->and($newReply->replied_to_user_id)->toBe($replyAuthor->id);
});

it('comments on a comment that is not deleted exactly as before', function (): void {
    $comment = createComment();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $comment->id,
        ])
        ->assertRedirect();

    expect($comment->comments()->count())->toBe(1);
});
