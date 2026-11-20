<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Notification\Enums\NotificationType;
use App\Modules\Notification\Notifications\FriendRequestAcceptedNotification;
use App\Modules\Notification\Notifications\FriendRequestReceivedNotification;
use App\Modules\Notification\Resources\NotificationResource;
use App\User\Enums\AccountStatus;
use App\User\Enums\PreferredLocale;
use Illuminate\Support\Facades\Notification;

it('queues both friendship notifications through database and broadcast after the commit', function (): void {
    Notification::fake();

    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);

    $receiver->notify(new FriendRequestReceivedNotification($user->id, $user->name, $friendship->id));

    Notification::assertSentTo(
        $receiver,
        FriendRequestReceivedNotification::class,
        fn (FriendRequestReceivedNotification $friendRequestReceivedNotification): bool => $friendRequestReceivedNotification->via($receiver) === ['database', 'broadcast']
            && $friendRequestReceivedNotification->afterCommit === true
            && $friendRequestReceivedNotification->databaseType($receiver) === NotificationType::FriendRequestReceived->value
            && $friendRequestReceivedNotification->broadcastType() === NotificationType::FriendRequestReceived->value,
    );

    $friendship->forceFill(['status' => FriendshipStatus::Accepted])->save();
    $user->notify(new FriendRequestAcceptedNotification($receiver->id, $receiver->name, $friendship->id));

    Notification::assertSentTo(
        $user,
        FriendRequestAcceptedNotification::class,
        fn (FriendRequestAcceptedNotification $friendRequestAcceptedNotification): bool => $friendRequestAcceptedNotification->via($user) === ['database', 'broadcast']
            && $friendRequestAcceptedNotification->afterCommit === true
            && $friendRequestAcceptedNotification->databaseType($user) === NotificationType::FriendRequestAccepted->value
            && $friendRequestAcceptedNotification->broadcastType() === NotificationType::FriendRequestAccepted->value,
    );
});

it('stores a received request in the language of the receiver and hides the friendship from the resource', function (PreferredLocale $preferredLocale): void {
    $user = createUser();
    $receiver = createUserPreferring($preferredLocale);
    $friendship = createFriendRequest($user, $receiver);

    Notification::sendNow($receiver, new FriendRequestReceivedNotification($user->id, $user->name, $friendship->id), ['database']);

    $databaseNotification = $receiver->notifications()->sole();

    expect($databaseNotification->type)->toBe(NotificationType::FriendRequestReceived->value)
        ->and($databaseNotification->data)->toBe([
            'summary' => str_replace(':actor', $user->name, translatedTextIn($preferredLocale, 'notification/center.summaries.friend_request_received')),
            'actors' => [['id' => $user->id, 'name' => $user->name]],
            'target' => ['postId' => null, 'commentId' => null, 'friendshipId' => $friendship->id],
        ])
        ->and(new NotificationResource($databaseNotification)->toArray(request())['target'])
        ->toBe(['postId' => null, 'commentId' => null]);
})->with([
    'English receiver' => [PreferredLocale::ENGLISH],
    'Spanish receiver' => [PreferredLocale::SPANISH],
]);

it('stores an accepted request in the language of the requester', function (PreferredLocale $preferredLocale): void {
    $user = createUserPreferring($preferredLocale);
    $receiver = createUser();
    $friendship = createFriendship($user, $receiver);

    Notification::sendNow($user, new FriendRequestAcceptedNotification($receiver->id, $receiver->name, $friendship->id), ['database']);

    $databaseNotification = $user->notifications()->sole();

    expect($databaseNotification->type)->toBe(NotificationType::FriendRequestAccepted->value)
        ->and($databaseNotification->data)->toBe([
            'summary' => str_replace(':actor', $receiver->name, translatedTextIn($preferredLocale, 'notification/center.summaries.friend_request_accepted')),
            'actors' => [['id' => $receiver->id, 'name' => $receiver->name]],
            'target' => ['postId' => null, 'commentId' => null, 'friendshipId' => $friendship->id],
        ]);
})->with([
    'English requester' => [PreferredLocale::ENGLISH],
    'Spanish requester' => [PreferredLocale::SPANISH],
]);

it('does not deliver a received request that is no longer pending', function (string $change): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);
    $notification = new FriendRequestReceivedNotification($user->id, $user->name, $friendship->id);

    match ($change) {
        'canceled' => $friendship->delete(),
        'accepted' => $friendship->forceFill(['status' => FriendshipStatus::Accepted])->save(),
        default => $receiver->forceFill(['status' => AccountStatus::Suspended])->save(),
    };

    expect($notification->shouldSend($receiver, 'database'))->toBeFalse();

    Notification::sendNow($receiver, $notification, ['database']);

    expect($receiver->notifications()->count())->toBe(0);
})->with([
    'canceled before delivery' => ['canceled'],
    'already accepted' => ['accepted'],
    'suspended receiver' => ['suspended'],
]);

it('does not deliver a received request to a user who is not its receiver', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user, createUser());

    expect(new FriendRequestReceivedNotification($user->id, $user->name, $friendship->id)->shouldSend($user, 'database'))
        ->toBeFalse();
});

it('does not deliver an accepted request when the friendship is no longer accepted', function (string $change): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendship($user, $receiver);
    $notification = new FriendRequestAcceptedNotification($receiver->id, $receiver->name, $friendship->id);

    match ($change) {
        'removed' => $friendship->delete(),
        default => $user->forceFill(['status' => AccountStatus::Suspended])->save(),
    };

    expect($notification->shouldSend($user, 'database'))->toBeFalse();
})->with([
    'removed friendship' => ['removed'],
    'suspended requester' => ['suspended'],
]);

it('broadcasts only the deletion of a received request', function (): void {
    $user = createUser();
    $friendRequestReceivedNotification = FriendRequestReceivedNotification::deletion('9f0c7f53-6f5d-4b55-9d84-2f1f6ba1f5e1');

    expect($friendRequestReceivedNotification->id)->toBe('9f0c7f53-6f5d-4b55-9d84-2f1f6ba1f5e1')
        ->and($friendRequestReceivedNotification->via($user))->toBe(['broadcast'])
        ->and($friendRequestReceivedNotification->shouldSend($user, 'broadcast'))->toBeTrue()
        ->and($friendRequestReceivedNotification->toArray($user))->toBe(['deleted' => true]);
});
