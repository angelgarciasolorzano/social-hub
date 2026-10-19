<?php

declare(strict_types=1);

use App\Modules\Comment\Requests\CommentUpdateRequest;
use SanderMuller\FluentValidation\Testing\FluentRulesTester;

it('passes with valid content', function (): void {
    FluentRulesTester::for(CommentUpdateRequest::class)
        ->actingAs(createUser())
        ->with(['content' => 'Un comentario corregido'])
        ->passes();
});

it('requires the content with the same message as when creating', function (): void {
    $fluentRulesTester = FluentRulesTester::for(CommentUpdateRequest::class)
        ->actingAs(createUser())
        ->with(['content' => ''])
        ->failsOnly('content', 'required');

    expect($fluentRulesTester->errors()->first('content'))->toBe('El comentario es obligatorio');
});

it('rejects content made only of control characters or whitespace', function (string $submittedContent): void {
    FluentRulesTester::for(CommentUpdateRequest::class)
        ->actingAs(createUser())
        ->with(['content' => $submittedContent])
        ->failsOnly('content', 'required');
})->with([
    'control characters' => ["\0\x01\x02\x7F"],
    'spaces and line breaks' => ["   \n\t  "],
]);

it('accepts content of exactly 1000 characters and rejects 1001', function (): void {
    FluentRulesTester::for(CommentUpdateRequest::class)
        ->actingAs(createUser())
        ->with(['content' => str_repeat('a', 1000)])
        ->passes();

    FluentRulesTester::for(CommentUpdateRequest::class)
        ->actingAs(createUser())
        ->with(['content' => str_repeat('a', 1001)])
        ->failsOnly('content', 'max');
});

it('measures the length after stripping control characters', function (): void {
    FluentRulesTester::for(CommentUpdateRequest::class)
        ->actingAs(createUser())
        ->with(['content' => str_repeat("a\0", 1000)])
        ->passes();
});

it('rejects content that is not a string without failing with a server error', function (mixed $submittedContent): void {
    FluentRulesTester::for(CommentUpdateRequest::class)
        ->actingAs(createUser())
        ->with(['content' => $submittedContent])
        ->failsWith('content');
})->with([
    'an array' => [['no', 'es', 'texto']],
    'a number' => [123],
]);

it('only validates the content', function (): void {
    expect(array_keys(new CommentUpdateRequest()->rules()))->toBe(['content']);
});
