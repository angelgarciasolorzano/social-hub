<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Resources\CommentResource;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Hypothesis 3: content handling ----------------------------------------------

it('[H3] rejects whitespace-only content', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => "   \n\t  ",
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasErrors('content');

    expect(Comment::query()->count())->toBe(0);
});

it('[H3] strips control characters and trims the content of a reply to a reply', function (): void {
    $replyComment = createCommentOn(createComment());

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => "  Hola\x00 mundo\x07  ",
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $replyComment->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Comment::query()->latest('id')->firstOrFail()->content)->toBe('Hola mundo');
});

it('[H3] never renders comments as raw HTML in the frontend', function (): void {
    $frontendFiles = collect(File::allFiles(base_path('resources/js/modules/comment')));

    $offenders = $frontendFiles
        ->filter(fn (SplFileInfo $file): bool => str_contains((string) file_get_contents($file->getPathname()), 'dangerouslySetInnerHTML'))
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->values()
        ->all();

    expect($frontendFiles)->not->toBeEmpty()
        ->and($offenders)->toBeEmpty();
});

// Hypothesis 7: user data exposure --------------------------------------------

it('[H7] does not expose private user data in the serialized comments', function (): void {
    $user = createUser();
    $post = createPost();
    createCommentOn($post, $user);

    $cursorPaginator = $post->comments()->with('user')->cursorPaginate(10)
        ->through(fn (Comment $comment): array => new CommentResource($comment)->resolve(request()));
    $payload = (string) json_encode($cursorPaginator);

    expect($payload)->toContain($user->name)
        ->and($payload)->not->toContain($user->email)
        ->and($payload)->not->toContain('password')
        ->and($payload)->not->toContain('remember_token');
});

// Hypothesis 8: authorization and forged input --------------------------------

it('[H8] exposes only the store, update, destroy and like routes for comments', function (): void {
    $commentRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'comments'))
        ->map(fn (Illuminate\Routing\Route $route): string => (string) $route->getName())
        ->sort()
        ->values()
        ->all();

    expect($commentRoutes)->toBe(['comments.destroy', 'comments.like', 'comments.store', 'comments.update']);
});

it('[H8] ignores a forged user_id and attributes the comment to the authenticated user', function (): void {
    $user = createUser();
    $victimUser = createUser();
    $post = createPost();

    $this->actingAs($user)
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
            'user_id' => $victimUser->id,
        ]);

    expect(Comment::query()->sole()->user_id)->toBe($user->id);
});

it('[H8] ignores a forged replied_to_user_id and mentions the author of the replied comment', function (): void {
    $replyAuthorUser = createUser();
    $victimUser = createUser();
    $replyComment = createCommentOn(createComment(), $replyAuthorUser);

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Una respuesta a una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $replyComment->id,
            'replied_to_user_id' => $victimUser->id,
        ]);

    expect(Comment::query()->latest('id')->firstOrFail()->replied_to_user_id)->toBe($replyAuthorUser->id);
});

it('[H8] never nests replies deeper than one level when replying in a chain', function (): void {
    $user = createUser();
    $comment = createComment();
    $lastReply = createCommentOn($comment);

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)->post(route('comments.store'), [
            'content' => 'Respuesta en cadena',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $lastReply->id,
        ]);

        $lastReply = Comment::query()->latest('id')->firstOrFail();
    }

    $replyIds = Comment::query()->where('commentable_type', Comment::MORPH_NAME)->pluck('id')->all();

    expect($comment->comments()->count())->toBe(4)
        ->and(Comment::query()->where('commentable_type', Comment::MORPH_NAME)->whereIn('commentable_id', $replyIds)->count())->toBe(0);
});
