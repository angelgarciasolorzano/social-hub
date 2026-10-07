<?php

declare(strict_types=1);

use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;

it('maps each case to its morph name and model class', function (CommentType $commentType, string $value, string $modelClass): void {
    expect($commentType->value)->toBe($value)
        ->and($commentType->modelClass())->toBe($modelClass);
})->with([
    'post' => [CommentType::POST, Post::MORPH_NAME, Post::class],
    'comment' => [CommentType::COMMENT, Comment::MORPH_NAME, Comment::class],
]);

it('only supports the post and comment types', function (): void {
    expect(array_column(CommentType::cases(), 'value'))->toBe(['post', 'comment'])
        ->and(fn (): CommentType => CommentType::from('user'))->toThrow(ValueError::class)
        ->and(fn (): CommentType => CommentType::from(''))->toThrow(ValueError::class);
});
