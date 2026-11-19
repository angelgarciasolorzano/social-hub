<?php

declare(strict_types=1);

use App\Modules\Friendship\Database\Factories\FriendRequestDeclineFactory;
use App\Modules\Friendship\Models\FriendRequestDecline;
use App\Modules\Friendship\Models\Friendship;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

const FRIEND_REQUEST_COOLDOWN_DAYS = 7;

/**
 * Record that the receiver declined the requester's request the given number of days ago.
 */
function createFriendRequestDecline(User $requester, User $receiver, int $daysAgo = 0): FriendRequestDecline
{
    /** @var FriendRequestDeclineFactory $factory */
    $factory = FriendRequestDecline::factory();

    return $factory->between($requester, $receiver)->declinedDaysAgo($daysAgo)->createOne();
}

it('rejects a second row for the same pair when the other user sends it', function (): void {
    $user = createUser();
    $receiver = createUser();
    createFriendRequest($user, $receiver);

    expect(fn (): Friendship => createFriendRequest($receiver, $user))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and(Friendship::query()->count())->toBe(1);
});

it('rejects a second row for the same pair sent in the same direction', function (): void {
    $user = createUser();
    $receiver = createUser();
    createFriendRequest($user, $receiver);

    expect(fn (): Friendship => createFriendship($user, $receiver))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('lets two different users send a request to the same receiver', function (): void {
    $user = createUser();
    createFriendRequest(createUser(), $user);
    createFriendRequest(createUser(), $user);

    expect(Friendship::query()->where('receiver_id', $user->id)->count())->toBe(2);
});

it('orders the pair in the generated columns whoever sent the request', function (): void {
    $lowerUser = createUser();
    $higherUser = createUser();

    $friendship = createFriendRequest($higherUser, $lowerUser)->refresh();

    expect($friendship->user_low_id)->toBe($lowerUser->id)
        ->and($friendship->user_high_id)->toBe($higherUser->id);
});

it('finds the row of a pair in either order and no other pair', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);
    createFriendRequest($user, createUser());

    expect(Friendship::query()->between($user, $receiver)->sole()->is($friendship))->toBeTrue()
        ->and(Friendship::query()->between($receiver, $user)->sole()->is($friendship))->toBeTrue();
});

it('lists the rows where the user sent or received the request', function (): void {
    $user = createUser();
    $sentRequest = createFriendRequest($user);
    $friendship = createFriendship(createUser(), $user);
    createFriendRequest();

    expect(Friendship::query()->involving($user)->orderBy('id')->pluck('id')->all())
        ->toBe([$sentRequest->id, $friendship->id]);
});

it('separates pending requests from accepted friendships', function (): void {
    $friendRequest = createFriendRequest();
    $friendship = createFriendship();

    expect(Friendship::query()->pending()->pluck('id')->all())->toBe([$friendRequest->id])
        ->and(Friendship::query()->accepted()->pluck('id')->all())->toBe([$friendship->id])
        ->and($friendRequest->isPending())->toBeTrue()
        ->and($friendship->isAccepted())->toBeTrue();
});

it('returns the other user of the row from either side', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);

    expect($friendship->otherUserId($user))->toBe($receiver->id)
        ->and($friendship->otherUserId($receiver))->toBe($user->id);
});

it('rejects asking for the other user of a row the user is not part of', function (): void {
    $friendship = createFriendRequest();
    $user = createUser();

    expect($friendship->involves($user))->toBeFalse()
        ->and(fn (): int => $friendship->otherUserId($user))->toThrow(InvalidArgumentException::class);
});

it('keeps a decline active only within the cooldown', function (int $daysAgo, bool $isActive): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00'));
    $user = createUser();
    $receiver = createUser();
    createFriendRequestDecline($user, $receiver, $daysAgo);

    expect(FriendRequestDecline::query()->activeFor($user, $receiver, FRIEND_REQUEST_COOLDOWN_DAYS)->exists())
        ->toBe($isActive);
})->with([
    'declined today' => [0, true],
    'declined six days ago' => [6, true],
    'declined exactly seven days ago' => [7, false],
    'declined eight days ago' => [8, false],
]);

it('does not apply a decline to the opposite direction', function (): void {
    $user = createUser();
    $receiver = createUser();
    createFriendRequestDecline($user, $receiver);

    expect(FriendRequestDecline::query()->activeFor($receiver, $user, FRIEND_REQUEST_COOLDOWN_DAYS)->exists())
        ->toBeFalse();
});

it('deletes the friendships and declines of a deleted user in both roles', function (): void {
    $user = createUser();
    $otherUser = createUser();
    createFriendRequest($user, $otherUser);
    createFriendship(createUser(), $user);
    createFriendRequestDecline($user, $otherUser);
    createFriendRequestDecline(createUser(), $user);
    $unrelatedFriendship = createFriendship();

    $user->delete();

    expect(Friendship::query()->pluck('id')->all())->toBe([$unrelatedFriendship->id])
        ->and(FriendRequestDecline::query()->exists())->toBeFalse();
});
