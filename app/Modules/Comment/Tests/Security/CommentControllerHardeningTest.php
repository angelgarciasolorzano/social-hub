<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;

it('still lets users comment on a post', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->count())->toBe(1);
});

it('still lets users reply to a top-level comment', function (): void {
    $comment = createComment();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $comment->id,
        ])
        ->assertSessionHasNoErrors();

    expect($comment->repliesCount())->toBe(1);
});

it('rejects a reply to a reply with a validation error on commentable_id', function (): void {
    $comment = createComment();
    $reply = createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Una respuesta a una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $reply->id,
        ])
        ->assertSessionHasErrors(['commentable_id' => 'No se puede responder a una respuesta.']);

    expect($reply->repliesCount())->toBe(0)
        ->and($comment->repliesCount())->toBe(1);
});

it('still returns 404 when replying to a comment that does not exist', function (): void {
    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => 999999,
        ])
        ->assertNotFound();
});

it('does not keep an unreachable error branch that bypasses Inertia flash', function (): void {
    $source = (string) file_get_contents(app_path('Modules/Comment/Controllers/CommentController.php'));

    expect($source)->not->toContain('back()->with(')
        ->and($source)->not->toContain('Relation::getMorphedModel');
});
