<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Services\FriendshipService;
use App\Modules\Notification\Enums\NotificationType;
use App\Modules\Notification\Notifications\FriendRequestAcceptedNotification;
use App\Modules\Notification\Notifications\FriendRequestReceivedNotification;
use App\User\Enums\AccountStatus;
use Illuminate\Support\Facades\Notification;

it('notifies only the receiver when a request is sent', function (): void {
    $user = createUser();
    $receiver = createUser();

    expect(resolve(FriendshipService::class)->send($user, $receiver))->toBe(FriendshipOperationResult::Sent);

    $friendship = Friendship::query()->sole();

    expect($user->notifications()->count())->toBe(0)
        ->and($receiver->notifications()->sole()->type)->toBe(NotificationType::FriendRequestReceived->value)
        ->and($receiver->notifications()->sole()->data['target'])->toBe([
            'postId' => null,
            'commentId' => null,
            'friendshipId' => $friendship->id,
        ]);
});

it('notifies the requester and removes the unread request of the receiver when it is accepted', function (): void {
    $user = createUser();
    $receiver = createUser();
    resolve(FriendshipService::class)->send($user, $receiver);
    $friendship = Friendship::query()->sole();
    $receivedNotificationId = $receiver->notifications()->sole()->id;

    Notification::fake();

    expect(resolve(FriendshipService::class)->accept($friendship, $receiver))->toBe(FriendshipOperationResult::Accepted);

    Notification::assertSentTo($user, FriendRequestAcceptedNotification::class);
    Notification::assertSentTo(
        $receiver,
        FriendRequestReceivedNotification::class,
        fn (FriendRequestReceivedNotification $friendRequestReceivedNotification): bool => $friendRequestReceivedNotification->id === $receivedNotificationId
            && $friendRequestReceivedNotification->toArray($receiver) === ['deleted' => true],
    );

    expect($receiver->notifications()->count())->toBe(0);
});

it('stores the accepted request for the requester', function (): void {
    $user = createUser();
    $receiver = createUser();
    resolve(FriendshipService::class)->send($user, $receiver);

    resolve(FriendshipService::class)->accept(Friendship::query()->sole(), $receiver);

    expect($user->notifications()->sole()->type)->toBe(NotificationType::FriendRequestAccepted->value)
        ->and($receiver->notifications()->count())->toBe(0);
});

it('removes the unread request and broadcasts its deletion when it is :dataset', function (string $action): void {
    $user = createUser();
    $receiver = createUser();
    resolve(FriendshipService::class)->send($user, $receiver);
    $friendship = Friendship::query()->sole();
    $receivedNotificationId = $receiver->notifications()->sole()->id;

    Notification::fake();

    $action === 'declined'
        ? resolve(FriendshipService::class)->decline($friendship, $receiver)
        : resolve(FriendshipService::class)->cancel($friendship, $user);

    Notification::assertSentTo(
        $receiver,
        FriendRequestReceivedNotification::class,
        fn (FriendRequestReceivedNotification $friendRequestReceivedNotification): bool => $friendRequestReceivedNotification->id === $receivedNotificationId
            && $friendRequestReceivedNotification->via($receiver) === ['broadcast'],
    );
    Notification::assertNothingSentTo($user);

    expect($receiver->notifications()->count())->toBe(0);
})->with([
    'declined' => ['declined'],
    'canceled' => ['canceled'],
]);

it('keeps a request the receiver already read', function (): void {
    $user = createUser();
    $receiver = createUser();
    resolve(FriendshipService::class)->send($user, $receiver);
    $receiver->unreadNotifications()->sole()->markAsRead();

    Notification::fake();

    resolve(FriendshipService::class)->decline(Friendship::query()->sole(), $receiver);

    Notification::assertNothingSent();

    expect($receiver->notifications()->count())->toBe(1);
});

it('removes nothing when a friendship is removed', function (): void {
    $user = createUser();
    $receiver = createUser();
    resolve(FriendshipService::class)->send($user, $receiver);
    $friendship = Friendship::query()->sole();
    resolve(FriendshipService::class)->accept($friendship, $receiver);

    Notification::fake();

    expect(resolve(FriendshipService::class)->remove($friendship, $receiver))->toBe(FriendshipOperationResult::Removed);

    Notification::assertNothingSent();

    expect($user->notifications()->count())->toBe(1);
});

it('notifies nobody when the service refuses the request because :dataset', function (string $refusal): void {
    $user = createUser();
    $receiver = createUser();

    if ($refusal === 'it is repeated') {
        resolve(FriendshipService::class)->send($user, $receiver);
    }

    if ($refusal === 'the cooldown is active') {
        resolve(FriendshipService::class)->send($user, $receiver);
        resolve(FriendshipService::class)->decline(Friendship::query()->sole(), $receiver);
    }

    if ($refusal === 'the receiver is suspended') {
        $receiver->forceFill(['status' => AccountStatus::Suspended])->save();
    }

    $notificationCount = $receiver->notifications()->count();

    Notification::fake();

    expect(resolve(FriendshipService::class)->send($user, $receiver))->not->toBe(FriendshipOperationResult::Sent);

    Notification::assertNothingSent();

    expect($receiver->notifications()->count())->toBe($notificationCount);
})->with([
    'it is repeated' => ['it is repeated'],
    'the cooldown is active' => ['the cooldown is active'],
    'the receiver is suspended' => ['the receiver is suspended'],
]);

it('removes nothing when a request is no longer pending', function (): void {
    $user = createUser();
    $receiver = createUser();
    resolve(FriendshipService::class)->send($user, $receiver);
    $friendship = Friendship::query()->sole();
    resolve(FriendshipService::class)->cancel($friendship, $user);

    Notification::fake();

    expect(resolve(FriendshipService::class)->decline($friendship, $receiver))->toBe(FriendshipOperationResult::NoLongerPending);

    Notification::assertNothingSent();
});
