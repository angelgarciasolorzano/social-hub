<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Edit the given comment as the given user, or as a guest when none is given.
 *
 * @return TestResponse<Response>
 */
function editCommentAs(TestCase $testCase, ?User $user, Comment|int $comment, string $content): TestResponse
{
    if ($user instanceof User) {
        $testCase->actingAs($user);
    }

    return $testCase->patch(route('comments.update', $comment), ['content' => $content]);
}

it('lets the author edit the comment and flashes a success message', function (): void {
    $user = createUser();
    $comment = createComment($user);

    $testResponse = editCommentAs($this, $user, $comment, 'Texto corregido');

    $testResponse->assertRedirect();
    $testResponse->assertInertiaFlash('type', 'success');
    $testResponse->assertInertiaFlash('message', 'Comentario actualizado correctamente');

    expect($comment->fresh()?->content)->toBe('Texto corregido');
});

it('marks the comment as edited', function (): void {
    $user = createUser();
    $comment = createComment($user);

    editCommentAs($this, $user, $comment, 'Texto corregido');

    expect($comment->fresh()?->edited_at)->not->toBeNull();
});

it('does not mark the comment as edited when the content is the same', function (): void {
    $user = createUser();
    $comment = createComment($user, ['content' => 'Mismo texto']);

    editCommentAs($this, $user, $comment, 'Mismo texto');

    expect($comment->fresh()?->edited_at)->toBeNull();
});

it('stores the normalized content', function (): void {
    $user = createUser();
    $comment = createComment($user);

    editCommentAs($this, $user, $comment, "  Ho\0la\tmundo \n");

    expect($comment->fresh()?->content)->toBe("Hola\tmundo");
});

it('lets the author edit a reply', function (): void {
    $user = createUser();
    $comment = createCommentOn(createComment(), $user);

    editCommentAs($this, $user, $comment, 'Respuesta corregida')->assertRedirect();

    expect($comment->fresh()?->content)->toBe('Respuesta corregida');
});

it('answers 403 to another user and leaves the comment untouched', function (): void {
    $comment = createComment(createUser(), ['content' => 'Original']);

    editCommentAs($this, createUser(), $comment, 'Intento ajeno')->assertForbidden();

    expect($comment->fresh()?->content)->toBe('Original')
        ->and($comment->fresh()?->edited_at)->toBeNull();
});

it('answers 403 to another user before validating the content', function (): void {
    $comment = createComment();

    editCommentAs($this, createUser(), $comment, '')->assertForbidden();
});

it('redirects guests to the login page without editing', function (): void {
    $comment = createComment(attributes: ['content' => 'Original']);

    editCommentAs($this, null, $comment, 'Intento anónimo')->assertRedirect(route('login'));

    expect($comment->fresh()?->content)->toBe('Original');
});

it('answers 404 when the comment does not exist', function (): void {
    editCommentAs($this, createUser(), 999999, 'Texto')->assertNotFound();
});

it('answers 404 when the comment was already deleted', function (): void {
    $user = createUser();
    $comment = createComment($user, ['content' => 'Original']);
    $comment->delete();

    editCommentAs($this, $user, $comment->id, 'Texto')->assertNotFound();

    expect(Comment::withTrashed()->find($comment->id)?->content)->toBe('Original');
});

it('rejects an empty or too long content and leaves the comment untouched', function (string $submittedContent): void {
    $user = createUser();
    $comment = createComment($user, ['content' => 'Original']);

    editCommentAs($this, $user, $comment, $submittedContent)->assertSessionHasErrors('content');

    expect($comment->fresh()?->content)->toBe('Original')
        ->and($comment->fresh()?->edited_at)->toBeNull();
})->with([
    'empty' => [''],
    'only whitespace' => ["  \n "],
    '1001 characters' => [str_repeat('a', 1001)],
]);
