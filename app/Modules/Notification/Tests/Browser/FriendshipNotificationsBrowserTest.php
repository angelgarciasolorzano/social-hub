<?php

declare(strict_types=1);

use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Services\FriendshipService;
use App\User\Enums\PreferredLocale;
use Tests\TestCase;

it('lists a received friend request in the history and links it to the received tab', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = createUser();
    $receiver = createUser();
    resolve(FriendshipService::class)->send($user, $receiver);

    $testCase->actingAs($receiver);

    visit(route('notifications.index', absolute: false))
        ->assertSee(str_replace(':actor', $user->name, translatedTextIn(PreferredLocale::ENGLISH, 'notification/center.summaries.friend_request_received')))
        ->click(translatedTextIn(PreferredLocale::ENGLISH, 'notification/center.actions.open_friend_requests'))
        ->assertPathIs('/friendships')
        ->assertQueryStringHas('tab', 'received')
        ->assertSee($user->name)
        ->assertNoJavaScriptErrors();
});

it('removes a received friend request from the history once its sender cancels it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = createUser();
    $receiver = createUser();
    resolve(FriendshipService::class)->send($user, $receiver);
    resolve(FriendshipService::class)->cancel(Friendship::query()->sole(), $user);

    $testCase->actingAs($receiver);

    visit(route('notifications.index', absolute: false))
        ->assertDontSee(str_replace(':actor', $user->name, translatedTextIn(PreferredLocale::ENGLISH, 'notification/center.summaries.friend_request_received')))
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'notification/center.history.empty_title'))
        ->assertNoJavaScriptErrors();
});
