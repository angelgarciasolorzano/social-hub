<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Services\FriendshipService;
use App\User\Enums\AccountStatus;
use Spatie\Activitylog\Models\Activity;

/**
 * The `friendships` activities as [event, causer id, subject type, properties], oldest first.
 *
 * @return list<array{string|null, int|null, string|null, array<array-key, mixed>}>
 */
function recordedFriendshipActivities(): array
{
    return array_values(Activity::query()
        ->where('log_name', 'friendships')
        ->oldest('id')
        ->get()
        ->map(fn (Activity $activity): array => [
            $activity->event,
            $activity->causer_id,
            $activity->subject_type,
            $activity->properties?->only(['friendship_id', 'target_user_id'])->all() ?? [],
        ])
        ->all());
}

it('records a sent and an accepted request with the other person as target', function (): void {
    $user = createUser();
    $receiver = createUser();

    resolve(FriendshipService::class)->send($user, $receiver);
    $friendship = Friendship::query()->sole();
    resolve(FriendshipService::class)->accept($friendship, $receiver);

    expect(recordedFriendshipActivities())->toBe([
        ['friendship.request_sent', $user->id, null, ['friendship_id' => $friendship->id, 'target_user_id' => $receiver->id]],
        ['friendship.request_accepted', $receiver->id, null, ['friendship_id' => $friendship->id, 'target_user_id' => $user->id]],
    ]);
});

it('records a declined, a canceled and a removed friendship with the actor as causer', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendshipService = resolve(FriendshipService::class);

    $declinedRequest = createFriendRequest($user, $receiver);
    $friendshipService->decline($declinedRequest, $receiver);

    $canceledRequest = createFriendRequest($receiver, $user);
    $friendshipService->cancel($canceledRequest, $receiver);

    $friendship = createFriendship($user, $receiver);
    $friendshipService->remove($friendship, $user);

    expect(recordedFriendshipActivities())->toBe([
        ['friendship.request_declined', $receiver->id, null, ['friendship_id' => $declinedRequest->id, 'target_user_id' => $user->id]],
        ['friendship.request_canceled', $receiver->id, null, ['friendship_id' => $canceledRequest->id, 'target_user_id' => $user->id]],
        ['friendship.removed', $user->id, null, ['friendship_id' => $friendship->id, 'target_user_id' => $receiver->id]],
    ]);
});

it('records nothing when the service refuses the action because :dataset', function (string $refusal): void {
    $user = createUser();
    $receiver = createUser();
    $friendshipService = resolve(FriendshipService::class);

    if ($refusal === 'the receiver is suspended') {
        $receiver->forceFill(['status' => AccountStatus::Suspended])->save();

        expect($friendshipService->send($user, $receiver))->toBe(FriendshipOperationResult::ReceiverUnavailable);
    }

    if ($refusal === 'the request is no longer pending') {
        $friendRequest = createFriendRequest($user, $receiver);
        $friendRequest->delete();

        expect($friendshipService->accept($friendRequest, $receiver))->toBe(FriendshipOperationResult::NoLongerPending);
    }

    expect(recordedFriendshipActivities())->toBeEmpty();
})->with([
    'the receiver is suspended' => ['the receiver is suspended'],
    'the request is no longer pending' => ['the request is no longer pending'],
]);
