<?php

declare(strict_types=1);

use App\Modules\Comment\Props\CommentThreadProps;
use Inertia\Testing\AssertableInertia as Assert;

it('renders an existing post with its comment and reply threads for an authenticated user', function (): void {
    $user = createUser();
    $viewer = createUser();
    $post = createPost($user, ['content' => 'A post with a comment thread.']);
    $comment = createCommentOn($post, $user);
    $reply = createCommentOn($comment, $viewer);
    createLike($viewer, $post);

    $url = route('posts.show', [
        'post' => $post,
        CommentThreadProps::COMMENTS_PARAMETER => $post->id,
        CommentThreadProps::REPLIES_PARAMETER => $comment->id,
    ]);

    $this->actingAs($viewer)
        ->get($url)
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('post/Show', shouldExist: false)
            ->has('post', fn (Assert $postAssert): Assert => $postAssert
                ->where('id', $post->id)
                ->where('content', 'A post with a comment thread.')
                ->where('commentsCount', 2)
                ->where('likesInfo', ['count' => 1, 'likedByMe' => true])
                ->where('user.id', $user->id)
                ->etc()
            )
            ->missing('comments')
            ->missing('replies')
        );

    $this->get($url, inertiaPartialHeaders($this, 'post/Show', 'comments'))
        ->assertOk()
        ->assertJsonCount(1, 'props.comments.data')
        ->assertJsonPath('props.comments.data.0.id', $comment->id)
        ->assertJsonPath('props.comments.next_cursor', null);

    $this->get($url, inertiaPartialHeaders($this, 'post/Show', 'replies'))
        ->assertOk()
        ->assertJsonCount(1, 'props.replies.data')
        ->assertJsonPath('props.replies.data.0.id', $reply->id)
        ->assertJsonPath('props.replies.next_cursor', null);
});

it('returns not found for a missing or deleted post', function (): void {
    $user = createUser();
    $deletedPost = createPost();
    $deletedPost->delete();

    $this->actingAs($user)
        ->get(route('posts.show', ['post' => 999999]))
        ->assertNotFound();

    $this->get(route('posts.show', ['post' => $deletedPost->id]))
        ->assertNotFound();
});

it('redirects a guest to login when opening a post', function (): void {
    $post = createPost();

    $this->get(route('posts.show', ['post' => $post]))
        ->assertRedirect(route('login'));
});
