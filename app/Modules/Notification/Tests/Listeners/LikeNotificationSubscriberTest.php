<?php

declare(strict_types=1);

use App\Modules\Like\Events\LikeToggled;
use App\Modules\Notification\Listeners\LikeNotificationSubscriber;
use App\Modules\Notification\Notifications\LikeNotification;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('groups likes on a post for its owner', function (): void {
    $user = createUser();
    $firstActor = createUser();
    $secondActor = createUser();
    $post = createPost($user);

    event(new LikeToggled($firstActor, $post, liked: true));
    event(new LikeToggled($secondActor, $post, liked: true));

    $databaseNotification = $user->notifications()->firstOrFail();

    expect($user->notifications()->count())->toBe(1)
        ->and($databaseNotification->type)->toBe(LikeNotification::TYPE)
        ->and($databaseNotification->data['actors'])->toBe([
            ['id' => $firstActor->id, 'name' => $firstActor->name],
            ['id' => $secondActor->id, 'name' => $secondActor->name],
        ]);
});

it('updates only the matching comment group when an actor unlikes it', function (): void {
    $user = createUser();
    $firstActor = createUser();
    $secondActor = createUser();
    $post = createPost($user);
    $firstComment = createCommentOn($post, $user);
    $secondComment = createCommentOn($post, $user);

    event(new LikeToggled($firstActor, $firstComment, liked: true));
    event(new LikeToggled($secondActor, $firstComment, liked: true));
    event(new LikeToggled($firstActor, $secondComment, liked: true));
    event(new LikeToggled($firstActor, $firstComment, liked: false));

    $notifications = $user->notifications()->where('type', LikeNotification::TYPE)->get();
    $firstGroup = $notifications->firstWhere('data.target.commentId', $firstComment->id);
    $secondGroup = $notifications->firstWhere('data.target.commentId', $secondComment->id);

    expect($notifications)->toHaveCount(2)
        ->and($firstGroup?->data['actors'])->toBe([
            ['id' => $secondActor->id, 'name' => $secondActor->name],
        ])
        ->and($secondGroup?->data['actors'])->toBe([
            ['id' => $firstActor->id, 'name' => $firstActor->name],
        ]);
});

it('does not create a notification when a user likes their own post or comment', function (): void {
    $user = createUser();
    $post = createPost($user);
    $comment = createCommentOn($post, $user);

    event(new LikeToggled($user, $post, liked: true));
    event(new LikeToggled($user, $comment, liked: true));

    expect($user->notifications()->count())->toBe(0);
});

it('rolls back a failed group write without changing another users like', function (): void {
    $user = createUser();
    $actor = createUser();
    $otherActor = createUser();
    $post = createPost($user);
    createLike($actor, $post);
    $otherActorsLike = createLike($otherActor, $post);
    $likeNotificationSubscriber = resolve(LikeNotificationSubscriber::class);

    DB::listen(function (QueryExecuted $queryExecuted): void {
        $sql = Str::lower($queryExecuted->sql);

        throw_if(str_starts_with($sql, 'insert') && str_contains($sql, 'notifications'), RuntimeException::class, 'Notification storage failed.');
    });

    $likeNotificationAttempt = function () use ($likeNotificationSubscriber, $actor, $post): void {
        $likeNotificationSubscriber->handleLikeToggled(new LikeToggled($actor, $post, liked: true));
    };

    expect($likeNotificationAttempt)->toThrow(RuntimeException::class, 'Notification storage failed.')
        ->and($user->notifications()
            ->count())
        ->toBe(0)
        ->and($otherActorsLike->fresh())->not->toBeNull();
});
