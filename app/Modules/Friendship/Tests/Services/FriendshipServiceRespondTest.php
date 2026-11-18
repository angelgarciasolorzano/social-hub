<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Events\FriendRequestAccepted;
use App\Modules\Friendship\Events\FriendshipEnded;
use App\Modules\Friendship\Models\FriendRequestDecline;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Services\FriendshipService;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Resolve the service from the container, as the controller does.
 */
function respondingFriendshipService(): FriendshipService
{
    return resolve(FriendshipService::class);
}

/**
 * Whether a FriendshipEnded event was dispatched with the given actor, other user and reason.
 */
function friendshipEndedWith(User $actor, User $otherUser, FriendshipOperationResult $friendshipOperationResult): bool
{
    return Event::dispatched(
        FriendshipEnded::class,
        fn (FriendshipEnded $friendshipEnded): bool => $friendshipEnded->actor->is($actor)
            && $friendshipEnded->otherUser->is($otherUser)
            && $friendshipEnded->reason === $friendshipOperationResult,
    )->isNotEmpty();
}

it('accepts a received request and announces it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $testCase->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00'));

    $user = createUser();
    $friendship = createFriendRequest(createUser(), $user);
    Event::fake([FriendRequestAccepted::class]);

    $friendshipOperationResult = respondingFriendshipService()->accept($friendship, $user);

    expect($friendshipOperationResult)->toBe(FriendshipOperationResult::Accepted)
        ->and($friendship->status)->toBe(FriendshipStatus::Accepted)
        ->and($friendship->accepted_at?->toDateTimeString())->toBe('2026-11-18 12:00:00');

    Event::assertDispatched(fn (FriendRequestAccepted $friendRequestAccepted): bool => $friendRequestAccepted->friendship->is($friendship));
});

it('answers that the request is no longer pending when it was already accepted elsewhere', function (): void {
    $user = createUser();
    $friendship = createFriendRequest(createUser(), $user);
    Friendship::query()->findOrFail($friendship->id)->forceFill(['status' => FriendshipStatus::Accepted, 'accepted_at' => now()])->save();
    Event::fake([FriendRequestAccepted::class]);

    expect(respondingFriendshipService()->accept($friendship, $user))->toBe(FriendshipOperationResult::NoLongerPending);

    Event::assertNotDispatched(FriendRequestAccepted::class);
});

it('refuses an accept by the requester', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user);

    expect(fn (): FriendshipOperationResult => respondingFriendshipService()->accept($friendship, $user))
        ->toThrow(LogicException::class)
        ->and($friendship->refresh()->isPending())->toBeTrue();
});

it('refuses a decline by the requester', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user);

    expect(fn (): FriendshipOperationResult => respondingFriendshipService()->decline($friendship, $user))
        ->toThrow(LogicException::class)
        ->and($friendship->refresh()->isPending())->toBeTrue();
});

it('refuses a cancel by the receiver', function (): void {
    $user = createUser();
    $friendship = createFriendRequest(createUser(), $user);

    expect(fn (): FriendshipOperationResult => respondingFriendshipService()->cancel($friendship, $user))
        ->toThrow(LogicException::class);
});

it('declines a received request, records the decline and announces it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $testCase->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00'));

    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);
    Event::fake([FriendshipEnded::class]);

    $friendshipOperationResult = respondingFriendshipService()->decline($friendship, $receiver);

    $friendRequestDecline = FriendRequestDecline::query()->sole();

    expect($friendshipOperationResult)->toBe(FriendshipOperationResult::Declined)
        ->and(Friendship::query()->exists())->toBeFalse()
        ->and($friendRequestDecline->requester_id)->toBe($user->id)
        ->and($friendRequestDecline->receiver_id)->toBe($receiver->id)
        ->and($friendRequestDecline->declined_at->toDateTimeString())->toBe('2026-11-18 12:00:00')
        ->and(friendshipEndedWith($receiver, $user, FriendshipOperationResult::Declined))->toBeTrue();
});

it('keeps one decline per pair and moves its date when the request is declined again', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $testCase->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00'));

    $user = createUser();
    $receiver = createUser();
    respondingFriendshipService()->decline(createFriendRequest($user, $receiver), $receiver);

    $testCase->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00'));
    respondingFriendshipService()->decline(createFriendRequest($user, $receiver), $receiver);

    expect(FriendRequestDecline::query()->sole()->declined_at->toDateTimeString())->toBe('2026-11-18 12:00:00');
});

it('cancels a sent request without starting a cooldown', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);
    Event::fake([FriendshipEnded::class]);

    expect(respondingFriendshipService()->cancel($friendship, $user))->toBe(FriendshipOperationResult::Canceled)
        ->and(Friendship::query()->exists())->toBeFalse()
        ->and(FriendRequestDecline::query()->exists())->toBeFalse()
        ->and(friendshipEndedWith($user, $receiver, FriendshipOperationResult::Canceled))->toBeTrue();
});

it('removes an accepted friendship from either side', function (bool $actorSentTheRequest): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendship($user, $receiver);
    [$actor, $otherUser] = $actorSentTheRequest ? [$user, $receiver] : [$receiver, $user];
    Event::fake([FriendshipEnded::class]);

    expect(respondingFriendshipService()->remove($friendship, $actor))->toBe(FriendshipOperationResult::Removed)
        ->and(Friendship::query()->exists())->toBeFalse()
        ->and(FriendRequestDecline::query()->exists())->toBeFalse()
        ->and(friendshipEndedWith($actor, $otherUser, FriendshipOperationResult::Removed))->toBeTrue();
})->with([
    'by the user who sent the request' => [true],
    'by the user who accepted it' => [false],
]);

it('does not remove a request that is still pending', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user);

    expect(respondingFriendshipService()->remove($friendship, $user))->toBe(FriendshipOperationResult::NoLongerPending)
        ->and($friendship->refresh()->isPending())->toBeTrue();
});

it('answers that a request declined in another tab is no longer pending', function (): void {
    $user = createUser();
    $friendship = createFriendRequest(createUser(), $user);
    Friendship::query()->findOrFail($friendship->id)->delete();
    Event::fake([FriendshipEnded::class]);

    expect(respondingFriendshipService()->decline($friendship, $user))->toBe(FriendshipOperationResult::NoLongerPending);

    Event::assertNotDispatched(FriendshipEnded::class);
});

it('answers that a request canceled in another tab is no longer pending', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user);
    Friendship::query()->findOrFail($friendship->id)->delete();
    Event::fake([FriendshipEnded::class]);

    expect(respondingFriendshipService()->cancel($friendship, $user))->toBe(FriendshipOperationResult::NoLongerPending);

    Event::assertNotDispatched(FriendshipEnded::class);
});
