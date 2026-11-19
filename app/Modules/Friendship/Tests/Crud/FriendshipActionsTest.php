<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Enums\FriendshipState;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\FriendRequestDecline;
use App\Modules\Friendship\Models\Friendship;
use App\User\Enums\PreferredLocale;
use Illuminate\Support\Facades\Route;

/**
 * The JSON body an action answers, with the pair's row id after the action (`null` when it has none) and
 * the message in the given language.
 *
 * @return array{result: string, state: string, friendshipId: int|null, message: string}
 */
function friendshipActionBody(
    FriendshipOperationResult $friendshipOperationResult,
    FriendshipState $friendshipState,
    ?int $friendshipId,
    PreferredLocale $preferredLocale = PreferredLocale::ENGLISH,
): array {
    return [
        'result' => $friendshipOperationResult->value,
        'state' => $friendshipState->value,
        'friendshipId' => $friendshipId,
        'message' => translatedTextIn($preferredLocale, $friendshipOperationResult->messageKey()),
    ];
}

it('sends a friend request', function (): void {
    $user = createUser();
    $receiver = createUser();

    $this->actingAs($user)
        ->postJson(route('friendships.store', $receiver))
        ->assertOk()
        ->assertExactJson(friendshipActionBody(
            FriendshipOperationResult::Sent,
            FriendshipState::RequestSent,
            Friendship::query()->between($user, $receiver)->sole()->id,
        ));

    expect(Friendship::query()->between($user, $receiver)->sole()->requester_id)->toBe($user->id);
});

it('answers 409 when the request was already sent', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);

    $this->actingAs($user)
        ->postJson(route('friendships.store', $receiver))
        ->assertConflict()
        ->assertExactJson(friendshipActionBody(FriendshipOperationResult::AlreadyPending, FriendshipState::RequestSent, $friendship->id));
});

it('answers 409 in the user language with the existing row when the other person already sent a request', function (): void {
    $user = createUserPreferring(PreferredLocale::SPANISH);
    $receiver = createUser();
    $friendship = createFriendRequest($receiver, $user);

    $this->actingAs($user)
        ->postJson(route('friendships.store', $receiver))
        ->assertConflict()
        ->assertExactJson(friendshipActionBody(
            FriendshipOperationResult::IncomingRequestExists,
            FriendshipState::RequestReceived,
            $friendship->id,
            PreferredLocale::SPANISH,
        ));
});

it('accepts a received request', function (): void {
    $user = createUser();
    $friendship = createFriendRequest(createUser(), $user);

    $this->actingAs($user)
        ->postJson(route('friendships.accept', $friendship))
        ->assertOk()
        ->assertExactJson(friendshipActionBody(FriendshipOperationResult::Accepted, FriendshipState::Friends, $friendship->id));

    expect($friendship->refresh()->status)->toBe(FriendshipStatus::Accepted);
});

it('declines a received request and starts the cooldown', function (): void {
    $user = createUser();
    $receiver = createUser();
    $friendship = createFriendRequest($user, $receiver);

    $this->actingAs($receiver)
        ->deleteJson(route('friendships.decline', $friendship))
        ->assertOk()
        ->assertExactJson(friendshipActionBody(FriendshipOperationResult::Declined, FriendshipState::None, null));

    expect(Friendship::query()->exists())->toBeFalse()
        ->and(FriendRequestDecline::query()->sole()->requester_id)->toBe($user->id);
});

it('cancels a sent request', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user);

    $this->actingAs($user)
        ->deleteJson(route('friendships.cancel', $friendship))
        ->assertOk()
        ->assertExactJson(friendshipActionBody(FriendshipOperationResult::Canceled, FriendshipState::None, null));

    expect(Friendship::query()->exists())->toBeFalse();
});

it('removes an accepted friendship', function (): void {
    $user = createUser();
    $friendship = createFriendship(createUser(), $user);

    $this->actingAs($user)
        ->deleteJson(route('friendships.destroy', $friendship))
        ->assertOk()
        ->assertExactJson(friendshipActionBody(FriendshipOperationResult::Removed, FriendshipState::None, null));

    expect(Friendship::query()->exists())->toBeFalse();
});

it('answers 404 when the row was already deleted', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user);

    $this->actingAs($user)->deleteJson(route('friendships.cancel', $friendship))->assertOk();

    $this->actingAs($user)
        ->deleteJson(route('friendships.cancel', $friendship))
        ->assertNotFound();
});

it('forbids sending a request to oneself', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->postJson(route('friendships.store', $user))
        ->assertForbidden();

    expect(Friendship::query()->exists())->toBeFalse();
});

it('forbids sending a request to a suspended account', function (): void {
    $this->actingAs(createUser())
        ->postJson(route('friendships.store', createSuspendedUser()))
        ->assertForbidden();

    expect(Friendship::query()->exists())->toBeFalse();
});

it('exposes exactly the friendship routes, each signed in, verified and with its own throttle', function (): void {
    $friendshipRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (Illuminate\Routing\Route $route): bool => str_starts_with((string) $route->getName(), 'friendships.'))
        ->mapWithKeys(fn (Illuminate\Routing\Route $route): array => [(string) $route->getName() => $route->gatherMiddleware()]);

    expect($friendshipRoutes->keys()->sort()->values()->all())->toBe([
        'friendships.accept',
        'friendships.cancel',
        'friendships.decline',
        'friendships.destroy',
        'friendships.search',
        'friendships.store',
    ]);

    foreach ($friendshipRoutes as $routeName => $middleware) {
        $ownThrottles = array_filter(
            $middleware,
            fn (mixed $name): bool => is_string($name) && preg_match('/^throttle:\d+,1,'.preg_quote($routeName, '/').'$/', $name) === 1,
        );

        expect($middleware)->toContain('auth')->toContain('verified')
            ->and($ownThrottles)->toHaveCount(1, "{$routeName} needs a throttle prefixed with its own name");
    }
});
