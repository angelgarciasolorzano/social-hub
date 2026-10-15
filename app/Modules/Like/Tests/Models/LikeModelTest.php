<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;

it('belongs to the user who gave it', function (): void {
    $user = createUser();
    $like = createLike($user);

    expect($like->user?->is($user))->toBeTrue()
        ->and($user->likes()->pluck('id')->all())->toBe([$like->id]);
});

it('belongs to the post it was given to', function (): void {
    $post = createPost();
    $like = createLike(likeable: $post);

    expect($like->likeable)->toBeInstanceOf(Post::class)
        ->and($like->likeable->getKey())->toBe($post->id)
        ->and($like->likeable_type)->toBe(Post::MORPH_NAME);
});

it('belongs to the comment it was given to', function (): void {
    $comment = createComment();
    $like = createLike(likeable: $comment);

    expect($like->likeable)->toBeInstanceOf(Comment::class)
        ->and($like->likeable->getKey())->toBe($comment->id)
        ->and($like->likeable_type)->toBe(Comment::MORPH_NAME);
});

it('exposes the morph name and column used by the morph map', function (): void {
    expect(Like::MORPH_NAME)->toBe('like')
        ->and(Like::MORPH_COLUMN)->toBe('likeable')
        ->and(Relation::getMorphedModel(Like::MORPH_NAME))->toBe(Like::class);
});

it('refuses to resolve a likeable type missing from the morph map', function (): void {
    $like = createLike();
    $like->forceFill(['likeable_type' => 'unregistered'])->save();

    expect(fn () => $like->fresh()?->likeable)->toThrow(ClassMorphViolationException::class);
});
