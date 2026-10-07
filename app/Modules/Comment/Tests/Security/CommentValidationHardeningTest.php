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

it('strips control characters from the stored content', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => "Ho\0la \x07mun\x1Fdo",
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->sole()->content)->toBe('Hola mundo');
});

it('keeps tabs and line breaks inside the content', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => "Línea 1\nLínea 2\tfin",
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->sole()->content)->toBe("Línea 1\nLínea 2\tfin");
});

it('trims the whitespace that remains after stripping control characters', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => "\0  hola  \x01",
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->sole()->content)->toBe('hola');
});

it('validates the length after stripping control characters', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => str_repeat("a\0", 1000),
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->sole()->content)->toHaveLength(1000);
});

it('keeps accented and emoji characters untouched', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Qué buen día ☀️ — ¡gracias!',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->sole()->content)->toBe('Qué buen día ☀️ — ¡gracias!');
});

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
