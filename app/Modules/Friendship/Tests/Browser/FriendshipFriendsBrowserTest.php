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

/**
 * The script that reads the number shown on the counter of a friends page tab, with its color classes.
 */
function friendsTabCountScript(string $tabLabel): string
{
    return '(() => { const count = [...document.querySelectorAll(\'[role="tab"]\')].find((tab) => tab.textContent.startsWith("'.$tabLabel.'"))?.querySelector(\'span[aria-hidden="true"]\'); return count === undefined || count === null ? null : count.textContent + "|" + count.classList.contains("bg-blue-100"); })()';
}

it('accepts a request from the requests block, removes a friend, switches tabs and accepts two requests at once from the cards', function (): void {
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

    $acceptLabel = translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.states.accept');
    $friendsTabLabel = translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.tabs.friends');

    $webpage = visit(route('friendships.index', absolute: false))
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.requests.title'))
        ->assertSee('Ana Request')
        ->assertSee('Carla Friend')
        ->assertScript(friendsBadgeScript(), '3')
        ->assertScript(friendsTabCountScript($friendsTabLabel), '1|true');

    $webpage->click('[role=region] li:has-text("Ana Request") button:has-text("'.$acceptLabel.'")')
        ->assertSee('Ana Request')
        ->assertScript('[...document.querySelectorAll("main article")].some((article) => article.textContent.includes("Ana Request"))')
        ->assertScript(friendsBadgeScript(), '2')
        ->assertScript(friendsTabCountScript($friendsTabLabel), '2|true');

    $webpage->click('[aria-label="'.str_replace(':name', 'Carla Friend', translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.menu.label')).'"]')
        ->click('[role="menuitem"]')
        ->assertSee(str_replace(':name', 'Carla Friend', translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.remove_dialog.title')))
        ->click('[role="dialog"] [data-slot="dialog-footer"] button:last-child')
        ->assertDontSee(str_replace(':name', 'Carla Friend', translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.remove_dialog.title')))
        ->assertDontSee('Carla Friend')
        ->assertSee('Ana Request')
        ->assertScript(friendsTabCountScript($friendsTabLabel), '1|true');

    $webpage->click('[role="tab"]:has-text("'.translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.tabs.received').'")')
        ->assertQueryStringHas('tab', 'received')
        ->assertSee('Bruno Request')
        ->assertSee('Dora Request')
        ->assertDontSee('Carla Friend')
        ->assertScript('document.querySelector(\'[role="tab"][aria-selected="true"]\')?.textContent?.startsWith("'.translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.tabs.received').'")');

    // Both rows' actions start in the same tick, so their callbacks come from the same render.
    $webpage->script('document.querySelectorAll("main article").forEach((article) => [...article.querySelectorAll("button")].find((button) => button.textContent === "'.$acceptLabel.'")?.click())');

    $webpage->assertDontSee('Bruno Request')
        ->assertDontSee('Dora Request')
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.states.received_empty_title'))
        ->assertScript('document.querySelector("main [role=region]") === null')
        ->assertScript(friendsBadgeScript(), null)
        ->assertNoJavaScriptErrors();

    expect($newerRequest->refresh()->status)->toBe(FriendshipStatus::Accepted)
        ->and($middleRequest->refresh()->status)->toBe(FriendshipStatus::Accepted)
        ->and($olderRequest->refresh()->status)->toBe(FriendshipStatus::Accepted)
        ->and(Friendship::query()->whereKey($friendship->id)->exists())->toBeFalse();
});
