<?php

declare(strict_types=1);

use App\Modules\Comment\Database\Factories\CommentFactory;
use App\Modules\Comment\Policies\CommentPolicy;
use Illuminate\Support\Facades\Gate;

it('resolves the CommentPolicy from the model attribute', function (): void {
    expect(Gate::getPolicyFor(createComment()))->toBeInstanceOf(CommentPolicy::class);
});

it('allows the author to :dataset', function (string $ability): void {
    $user = createUser();
    $comment = createComment($user);

    expect(Gate::forUser($user)->allows($ability, $comment))->toBeTrue();
})->with(['update', 'delete']);

it('denies another user to :dataset', function (string $ability): void {
    $comment = createComment(createUser());
    $user = createUser();

    expect(Gate::forUser($user)->allows($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

it('denies the author to :dataset a comment that was already deleted', function (string $ability): void {
    $user = createUser();
    $comment = CommentFactory::new()->for($user)->trashed()->createOne();

    expect(Gate::forUser($user)->allows($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

it('allows the author to :dataset a reply', function (string $ability): void {
    $user = createUser();
    $comment = createCommentOn(createComment(), $user);

    expect(Gate::forUser($user)->allows($ability, $comment))->toBeTrue();
})->with(['update', 'delete']);
