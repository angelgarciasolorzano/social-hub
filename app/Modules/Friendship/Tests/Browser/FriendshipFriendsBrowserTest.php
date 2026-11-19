<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\Friendship;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * Create a user with the given name for the friends page browser test.
 */
function friendsBrowserUserNamed(string $name): User
{
    $user = createUser();
    $user->forceFill(['name' => $name])->save();

    return $user;
}

/**
 * The script that reads the number shown on the Friends badge of the left sidebar, or null without one.
 */
function friendsBadgeScript(): string
{
    return 'document.querySelector(\'nav a[href="/friendships"] span[aria-hidden="true"]\')?.textContent ?? null';
}

it('accepts a request from the sidebar, removes a friend, switches tabs and accepts two requests at once from the list', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = createUser();
    $now = now();

    Date::setTestNow($now->copy()->subDay());
    $olderRequest = createFriendRequest(friendsBrowserUserNamed('Bruno Request'), $user);
    Date::setTestNow($now->copy()->subHours(12));
    $middleRequest = createFriendRequest(friendsBrowserUserNamed('Dora Request'), $user);
    Date::setTestNow($now->copy()->subHour());
    $newerRequest = createFriendRequest(friendsBrowserUserNamed('Ana Request'), $user);
    Date::setTestNow();

    $friendship = createFriendship($user, friendsBrowserUserNamed('Carla Friend'));

    $testCase->actingAs($user);

    $webpage = visit(route('home', absolute: false))
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'home/sidebar.friend_requests.title'))
        ->assertSee('Ana Request')
        ->assertScript(friendsBadgeScript(), '3');

    $webpage->click('aside li:first-of-type button:has-text("'.translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.states.accept').'")')
        ->assertDontSee('Ana Request')
        ->assertScript(friendsBadgeScript(), '2');

    $webpage = visit(route('friendships.index', absolute: false))
        ->assertSee('Carla Friend')
        ->assertSee('Ana Request');

    $webpage->click('[aria-label="'.str_replace(':name', 'Carla Friend', translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.menu.label')).'"]')
        ->click('[role="menuitem"]')
        ->assertSee(str_replace(':name', 'Carla Friend', translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.remove_dialog.title')))
        ->click('[role="dialog"] [data-slot="dialog-footer"] button:last-child')
        ->assertDontSee(str_replace(':name', 'Carla Friend', translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.remove_dialog.title')))
        ->assertDontSee('Carla Friend')
        ->assertSee('Ana Request');

    $webpage->click('[role="tab"]:has-text("'.translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.tabs.received').'")')
        ->assertQueryStringHas('tab', 'received')
        ->assertSee('Bruno Request')
        ->assertSee('Dora Request')
        ->assertDontSee('Carla Friend')
        ->assertScript('document.querySelector(\'[role="tab"][aria-selected="true"]\')?.textContent?.startsWith("'.translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.tabs.received').'")');

    // Both rows' actions start in the same tick, so their callbacks come from the same render.
    $webpage->script('document.querySelectorAll("main article").forEach((article) => [...article.querySelectorAll("button")].find((button) => button.textContent === "'.translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.states.accept').'")?.click())');

    $webpage->assertDontSee('Bruno Request')
        ->assertDontSee('Dora Request')
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.states.received_empty_title'))
        ->assertNoJavaScriptErrors();

    expect($newerRequest->refresh()->status)->toBe(FriendshipStatus::Accepted)
        ->and($middleRequest->refresh()->status)->toBe(FriendshipStatus::Accepted)
        ->and($olderRequest->refresh()->status)->toBe(FriendshipStatus::Accepted)
        ->and(Friendship::query()->whereKey($friendship->id)->exists())->toBeFalse();
});
