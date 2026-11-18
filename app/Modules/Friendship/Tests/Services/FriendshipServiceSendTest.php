<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Events\FriendRequestSent;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Services\FriendshipService;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Resolve the service from the container, as the controller does.
 */
function friendshipService(): FriendshipService
{
    return resolve(FriendshipService::class);
}

it('sends a pending request and announces it', function (): void {
    $user = createUser();
    $receiver = createUser();
    Event::fake([FriendRequestSent::class]);

    $friendshipOperationResult = friendshipService()->send($user, $receiver);

    $friendship = Friendship::query()->sole();

    expect($friendshipOperationResult)->toBe(FriendshipOperationResult::Sent)
        ->and($friendship->requester_id)->toBe($user->id)
        ->and($friendship->receiver_id)->toBe($receiver->id)
        ->and($friendship->status)->toBe(FriendshipStatus::Pending);

    Event::assertDispatched(fn (FriendRequestSent $friendRequestSent): bool => $friendRequestSent->friendship->is($friendship));
});

it('lets two different users send a request to the same receiver', function (): void {
    $user = createUser();

    expect(friendshipService()->send(createUser(), $user))->toBe(FriendshipOperationResult::Sent)
        ->and(friendshipService()->send(createUser(), $user))->toBe(FriendshipOperationResult::Sent)
        ->and(Friendship::query()->where('receiver_id', $user->id)->count())->toBe(2);
});

it('refuses a request the pair already has, without writing or announcing anything', function (Closure $existingRow, FriendshipOperationResult $friendshipOperationResult): void {
    $user = createUser();
    $receiver = createUser();
    $existingRow($user, $receiver);
    Event::fake([FriendRequestSent::class]);

    expect(friendshipService()->send($user, $receiver))->toBe($friendshipOperationResult)
        ->and(Friendship::query()->count())->toBe(1);

    Event::assertNotDispatched(FriendRequestSent::class);
})->with([
    'a pending request in the same direction' => [
        createFriendRequest(...),
        FriendshipOperationResult::AlreadyPending,
    ],
    'a pending request from the receiver' => [
        fn (User $requester, User $receiver): Friendship => createFriendRequest($receiver, $requester),
        FriendshipOperationResult::IncomingRequestExists,
    ],
    'an accepted friendship' => [
        fn (User $requester, User $receiver): Friendship => createFriendship($receiver, $requester),
        FriendshipOperationResult::AlreadyFriends,
    ],
]);

it('keeps the receiver request pending when the requester sends one back', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($receiver, $user);

    friendshipService()->send($user, $receiver);

    expect($friendship->refresh()->status)->toBe(FriendshipStatus::Pending)
        ->and($friendship->requester_id)->toBe($receiver->id);
});

it('refuses a request to a suspended account', function (): void {
    $user = createUser();

    expect(friendshipService()->send($user, createSuspendedUser()))->toBe(FriendshipOperationResult::ReceiverUnavailable)
        ->and(Friendship::query()->exists())->toBeFalse();
});

it('refuses a request to oneself', function (): void {
    $user = createUser();

    expect(fn (): FriendshipOperationResult => friendshipService()->send($user, $user))->toThrow(LogicException::class);
});

it('makes the requester wait after the receiver declines, and only in that direction', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $testCase->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00'));
    config(['module.friendship.decline_cooldown_days' => 7]);
    $user = createUser();
    $receiver = createUser();
    friendshipService()->decline(createFriendRequest($user, $receiver), $receiver);

    $testCase->travelTo(CarbonImmutable::parse('2026-11-24 12:00:00'));

    expect(friendshipService()->send($user, $receiver))->toBe(FriendshipOperationResult::CooldownActive)
        ->and(friendshipService()->send($receiver, $user))->toBe(FriendshipOperationResult::Sent);
});

it('lets the requester ask again once the cooldown is over', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $testCase->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00'));
    config(['module.friendship.decline_cooldown_days' => 7]);
    $user = createUser();
    $receiver = createUser();
    friendshipService()->decline(createFriendRequest($user, $receiver), $receiver);

    $testCase->travelTo(CarbonImmutable::parse('2026-11-26 12:00:00'));

    expect(friendshipService()->send($user, $receiver))->toBe(FriendshipOperationResult::Sent);
});

it('starts no cooldown when the requester cancels', function (): void {
    $user = createUser();
    $receiver = createUser();
    friendshipService()->cancel(createFriendRequest($user, $receiver), $user);

    expect(friendshipService()->send($user, $receiver))->toBe(FriendshipOperationResult::Sent);
});

it('refuses a request once the requester reaches the pending limit', function (): void {
    config(['module.friendship.max_pending_sent' => 2]);
    $user = createUser();
    createFriendRequest($user);
    createFriendRequest($user);
    createFriendship($user);

    expect(friendshipService()->send($user, createUser()))->toBe(FriendshipOperationResult::PendingLimitReached)
        ->and($user->sentFriendRequests()->count())->toBe(2);
});

it('answers with the state of the pair when another request wins the race to the unique index', function (): void {
    $user = createUser();
    $receiver = createUser();
    Friendship::creating(function (Friendship $friendship) use ($user, $receiver): void {
        if ($friendship->requester_id === $user->id) {
            Friendship::withoutEvents(fn (): Friendship => createFriendRequest($receiver, $user));
        }
    });

    try {
        $friendshipOperationResult = friendshipService()->send($user, $receiver);
    } finally {
        Friendship::flushEventListeners();
    }

    expect($friendshipOperationResult)->toBe(FriendshipOperationResult::IncomingRequestExists)
        ->and(Friendship::query()->sole()->requester_id)->toBe($receiver->id);
});
