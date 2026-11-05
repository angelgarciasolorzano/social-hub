<?php

declare(strict_types=1);

use App\Modules\Notification\Enums\NotificationType;
use App\Modules\Notification\Notifications\LikeNotification;
use App\Modules\Notification\Services\LikeNotificationGroupService;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Support\Facades\Lang;

it('groups distinct actors into one unread notification for each destination', function (): void {
    $user = createUserPreferring(PreferredLocale::ENGLISH);
    $firstActor = createUser();
    $secondActor = createUser();
    $post = createPost($user);
    $comment = createCommentOn($post, $user);
    $likeNotificationGroupService = resolve(LikeNotificationGroupService::class);

    $likeNotificationGroupService->sync($user, $firstActor, $post, liked: true);

    $databaseNotification = $user->notifications()->firstOrFail();
    $postGroupId = $databaseNotification->id;

    $likeNotificationGroupService->sync($user, $secondActor, $post, liked: true);
    $likeNotificationGroupService->sync($user, $firstActor, $comment, liked: true);

    $databaseNotification->refresh();
    $notifications = $user->notifications()->where('type', NotificationType::LikesGrouped->value)->get();
    $commentGroup = $notifications->firstWhere('data.target.commentId', $comment->id);

    expect($notifications)->toHaveCount(2)
        ->and($databaseNotification->id)->toBe($postGroupId)
        ->and($databaseNotification->data['actors'])->toBe([
            ['id' => $firstActor->id, 'name' => $firstActor->name],
            ['id' => $secondActor->id, 'name' => $secondActor->name],
        ])
        ->and($databaseNotification->data['summary'])->toBe(Lang::choice(
            'notification/center.summaries.liked_post',
            2,
            ['actor' => $secondActor->name, 'others' => 1],
            PreferredLocale::ENGLISH->value,
        ))
        ->and($commentGroup?->data['target'])->toBe([
            'postId' => $post->id,
            'commentId' => $comment->id,
        ]);
});

it('keeps actor snapshots unique when the same like is synchronized more than once', function (): void {
    $user = createUser();
    $actor = createUser();
    $post = createPost($user);
    $likeNotificationGroupService = resolve(LikeNotificationGroupService::class);

    $likeNotificationGroupService->sync($user, $actor, $post, liked: true);
    $likeNotificationGroupService->sync($user, $actor, $post, liked: true);

    $databaseNotification = $user->notifications()->firstOrFail();

    expect($user->notifications()->count())->toBe(1)
        ->and($databaseNotification->data['actors'])->toBe([
            ['id' => $actor->id, 'name' => $actor->name],
        ]);
});

it('formats the summary in the recipient preferred language', function (): void {
    $user = createUserPreferring(PreferredLocale::SPANISH);
    $actor = createUser();
    $post = createPost($user);
    $likeNotificationGroupService = resolve(LikeNotificationGroupService::class);
    app()->setLocale(PreferredLocale::ENGLISH->value);

    $likeNotificationGroupService->sync($user, $actor, $post, liked: true);

    $databaseNotification = $user->notifications()->firstOrFail();

    expect($databaseNotification->data['summary'])->toBe(Lang::choice(
        'notification/center.summaries.liked_post',
        1,
        ['actor' => $actor->name, 'others' => 0],
        PreferredLocale::SPANISH->value,
    ))
        ->and(app()->getLocale())->toBe(PreferredLocale::ENGLISH->value);
});

it('removes an unlike actor from the unread group and deletes the group when it becomes empty', function (): void {
    $user = createUser();
    $firstActor = createUser();
    $secondActor = createUser();
    $post = createPost($user);
    $likeNotificationGroupService = resolve(LikeNotificationGroupService::class);

    $likeNotificationGroupService->sync($user, $firstActor, $post, liked: true);
    $likeNotificationGroupService->sync($user, $secondActor, $post, liked: true);
    $likeNotificationGroupService->sync($user, $firstActor, $post, liked: false);

    $databaseNotification = $user->notifications()->firstOrFail();

    expect($databaseNotification->data['actors'])->toBe([
        ['id' => $secondActor->id, 'name' => $secondActor->name],
    ])
        ->and($databaseNotification->data['summary'])->toBe(Lang::choice(
            'notification/center.summaries.liked_post',
            1,
            ['actor' => $secondActor->name, 'others' => 0],
            $user->preferredLocale(),
        ));

    $likeNotificationGroupService->sync($user, $secondActor, $post, liked: false);

    expect($user->notifications()->count())->toBe(0);
});

it('preserves a read group and creates a new unread group for a later like', function (): void {
    $user = createUser();
    $firstActor = createUser();
    $secondActor = createUser();
    $post = createPost($user);
    $likeNotificationGroupService = resolve(LikeNotificationGroupService::class);

    $likeNotificationGroupService->sync($user, $firstActor, $post, liked: true);

    $databaseNotification = $user->notifications()->firstOrFail();
    $databaseNotification->markAsRead();

    $likeNotificationGroupService->sync($user, $secondActor, $post, liked: true);

    $notifications = $user->notifications()
        ->oldest()->get();
    $unreadGroup = $user->unreadNotifications()->firstOrFail();
    $databaseNotification->refresh();

    expect($notifications)->toHaveCount(2)
        ->and($unreadGroup->id)->not->toBe($databaseNotification->id)
        ->and($databaseNotification->read_at)->not->toBeNull()
        ->and($databaseNotification->data['actors'])->toBe([
            ['id' => $firstActor->id, 'name' => $firstActor->name],
        ])
        ->and($unreadGroup->data['actors'])->toBe([
            ['id' => $secondActor->id, 'name' => $secondActor->name],
        ]);
});

it('uses the root post id when grouping a like on a reply', function (): void {
    $user = createUser();
    $actor = createUser();
    $post = createPost($user);
    $rootComment = createCommentOn($post, $user);
    $comment = createCommentOn($rootComment, createUser());
    $likeNotificationGroupService = resolve(LikeNotificationGroupService::class);

    $likeNotificationGroupService->sync($user, $actor, $comment, liked: true);

    $databaseNotification = $user->notifications()->firstOrFail();

    expect($databaseNotification->data['target'])->toBe([
        'postId' => $post->id,
        'commentId' => $comment->id,
    ]);
});

it('serializes a grouped-like removal as a deletion marker', function (): void {
    $notification = new LikeNotification(
        [],
        ['postId' => 123, 'commentId' => null],
        'notification-id',
        isDeletionBroadcast: true,
    );

    expect($notification->toArray(new User))->toBe(['deleted' => true]);
});
