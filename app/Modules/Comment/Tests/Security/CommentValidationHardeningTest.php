<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Requests\CommentStoreRequest;
use App\Modules\Post\Models\Post;
use SanderMuller\FluentValidation\Testing\FluentRulesTester;

it('rejects content made only of control characters with the required message', function (): void {
    $fluentRulesTester = FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => "\0\x01\x02\x7F", 'commentable_type' => Post::MORPH_NAME, 'commentable_id' => 1])
        ->failsOnly('content', 'required');

    expect($fluentRulesTester->errors()->first('content'))->toBe('El comentario es obligatorio');
});

it('stores the content sanitized: :dataset', function (string $submittedContent, string $storedContent): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => $submittedContent,
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->sole()->content)->toBe($storedContent);
})->with([
    'control characters are stripped' => ["Ho\0la \x07mun\x1Fdo", 'Hola mundo'],
    'tabs and line breaks are kept' => ["Línea 1\nLínea 2\tfin", "Línea 1\nLínea 2\tfin"],
    'whitespace left after stripping is trimmed' => ["\0  hola  \x01", 'hola'],
    'the length is validated after stripping' => [str_repeat("a\0", 1000), str_repeat('a', 1000)],
    'accented and emoji characters are untouched' => ['Qué buen día ☀️ — ¡gracias!', 'Qué buen día ☀️ — ¡gracias!'],
]);

it('rejects non-string content without failing with a server error', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => ['no', 'es', 'texto'],
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasErrors('content');

    expect(Comment::query()->count())->toBe(0);
});
