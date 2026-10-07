<?php

declare(strict_types=1);

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

it('returns 404 and creates nothing when the commentable does not exist', function (): void {
    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => 999999,
        ])
        ->assertNotFound();

    expect(Comment::query()->count())->toBe(0);
});
