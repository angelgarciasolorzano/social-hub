<?php

declare(strict_types=1);

use App\Modules\Friendship\Models\Friendship;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Tests\TestCase;

/**
 * Create the given number of people whose names start with "Zeta Person".
 */
function createSearchablePeopleForBrowser(int $total): void
{
    foreach (range(1, $total) as $position) {
        $person = createUser();
        $person->forceFill(['name' => sprintf('Zeta Person %02d', $position)])->save();
    }
}

it('searches people, loads more results while scrolling and sends a friend request', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    config(['module.friendship.search_page_size' => 5]);
    $user = createUser();
    createSearchablePeopleForBrowser(12);

    $testCase->actingAs($user);

    $webpage = visit(route('friendships.search', absolute: false))
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'friendship/search.states.prompt_title'))
        ->type('q', 'Zeta')
        ->assertSee('Zeta Person 01')
        ->assertQueryStringHas('q', 'Zeta')
        ->assertDontSee('Zeta Person 12');

    $webpage->script('document.querySelector("main").closest(".overflow-y-auto").scrollTo(0, 100000)');
    $webpage->assertSee('Zeta Person 10');
    $webpage->script('document.querySelector("main").closest(".overflow-y-auto").scrollTo(0, 100000)');
    $webpage->assertSee('Zeta Person 12')
        ->assertNoJavaScriptErrors();

    $webpage->click(translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.states.add'))
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.states.request_sent'));

    expect(Friendship::query()->where('requester_id', $user->id)->count())->toBe(1)
        ->and(User::query()->whereKey(Friendship::query()->value('receiver_id'))->value('name'))->toStartWith('Zeta Person');
});
