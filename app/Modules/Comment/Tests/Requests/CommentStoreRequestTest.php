<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Requests\CommentStoreRequest;
use App\Modules\Post\Models\Post;
use SanderMuller\FluentValidation\Testing\FluentRulesTester;

it('is unauthorized for guests', function (): void {
    FluentRulesTester::for(CommentStoreRequest::class)
        ->with(['content' => 'Un comentario', 'commentable_type' => Post::MORPH_NAME, 'commentable_id' => 1])
        ->assertUnauthorized();
});

it('passes with a valid comment on a post or on another comment', function (string $commentableType): void {
    FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => 'Un comentario', 'commentable_type' => $commentableType, 'commentable_id' => 1])
        ->passes();
})->with([Post::MORPH_NAME, Comment::MORPH_NAME]);

it('requires the content with a custom message', function (): void {
    $fluentRulesTester = FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => '', 'commentable_type' => Post::MORPH_NAME, 'commentable_id' => 1])
        ->failsOnly('content', 'required');

    expect($fluentRulesTester->errors()->first('content'))->toBe('El comentario es obligatorio');
});

it('accepts content of exactly 1000 characters and rejects 1001', function (): void {
    FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => str_repeat('a', 1000), 'commentable_type' => Post::MORPH_NAME, 'commentable_id' => 1])
        ->passes();

    FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => str_repeat('a', 1001), 'commentable_type' => Post::MORPH_NAME, 'commentable_id' => 1])
        ->failsOnly('content', 'max');
});

it('only accepts the post and comment commentable types', function (): void {
    FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => 'Un comentario', 'commentable_type' => 'user', 'commentable_id' => 1])
        ->failsOnly('commentable_type');

    FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => 'Un comentario', 'commentable_id' => 1])
        ->failsOnly('commentable_type', 'required');
});

it('requires an integer commentable id', function (): void {
    FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => 'Un comentario', 'commentable_type' => Post::MORPH_NAME, 'commentable_id' => 'abc'])
        ->failsWith('commentable_id');

    FluentRulesTester::for(CommentStoreRequest::class)
        ->actingAs(createUser())
        ->with(['content' => 'Un comentario', 'commentable_type' => Post::MORPH_NAME])
        ->failsOnly('commentable_id', 'required');
});
