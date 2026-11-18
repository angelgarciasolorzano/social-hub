<?php

declare(strict_types=1);

use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Policies\FriendshipPolicy;
use App\User\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Whether the user may send a friend request to the receiver.
 */
function canSendFriendRequest(User $user, User $receiver): bool
{
    return Gate::forUser($user)->allows('create', [Friendship::class, $receiver]);
}

it('resolves the FriendshipPolicy from the model attribute', function (): void {
    expect(Gate::getPolicyFor(Friendship::class))->toBeInstanceOf(FriendshipPolicy::class);
});

it('allows sending a request to another active account', function (): void {
    expect(canSendFriendRequest(createUser(), createUser()))->toBeTrue();
});

it('denies sending a request to oneself', function (): void {
    $user = createUser();

    expect(canSendFriendRequest($user, $user))->toBeFalse();
});

it('denies sending a request to a suspended account', function (): void {
    expect(canSendFriendRequest(createUser(), createSuspendedUser()))->toBeFalse();
});

it('denies a suspended account sending a request', function (): void {
    expect(canSendFriendRequest(createSuspendedUser(), createUser()))->toBeFalse();
});

it('lets only the receiver :dataset a pending request', function (string $ability): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);

    expect(Gate::forUser($receiver)->allows($ability, $friendship))->toBeTrue()
        ->and(Gate::forUser($user)->allows($ability, $friendship))->toBeFalse()
        ->and(Gate::forUser(createUser())->allows($ability, $friendship))->toBeFalse();
})->with(['accept', 'decline']);

it('denies answering a request that was already accepted', function (string $ability): void {
    $user = createUser();
    $friendship = createFriendship(createUser(), $user);

    expect(Gate::forUser($user)->allows($ability, $friendship))->toBeFalse();
})->with(['accept', 'decline']);

it('lets only the requester cancel a pending request', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);

    expect(Gate::forUser($user)->allows('cancel', $friendship))->toBeTrue()
        ->and(Gate::forUser($receiver)->allows('cancel', $friendship))->toBeFalse()
        ->and(Gate::forUser(createUser())->allows('cancel', $friendship))->toBeFalse();
});

it('denies canceling a request that was already accepted', function (): void {
    $user = createUser();
    $friendship = createFriendship($user);

    expect(Gate::forUser($user)->allows('cancel', $friendship))->toBeFalse();
});

it('lets both friends remove an accepted friendship, and nobody else', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendship($user, $receiver);

    expect(Gate::forUser($user)->allows('delete', $friendship))->toBeTrue()
        ->and(Gate::forUser($receiver)->allows('delete', $friendship))->toBeTrue()
        ->and(Gate::forUser(createUser())->allows('delete', $friendship))->toBeFalse();
});

it('denies removing a request that is still pending', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user);

    expect(Gate::forUser($user)->allows('delete', $friendship))->toBeFalse();
});

it('lets only the two users of the row view it', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);

    expect(Gate::forUser($user)->allows('view', $friendship))->toBeTrue()
        ->and(Gate::forUser($receiver)->allows('view', $friendship))->toBeTrue()
        ->and(Gate::forUser(createUser())->allows('view', $friendship))->toBeFalse();
});

it('denies everyone to :dataset a row', function (string $ability): void {
    $user = createUser();
    $friendship = createFriendship($user);

    expect(Gate::forUser($user)->allows($ability, $friendship))->toBeFalse();
})->with(['update', 'restore', 'forceDelete']);
