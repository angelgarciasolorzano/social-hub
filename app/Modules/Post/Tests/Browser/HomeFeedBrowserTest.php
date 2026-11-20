<?php

declare(strict_types=1);

use App\User\Enums\PreferredLocale;
use Tests\TestCase;

it('shows the posts of friends, adds the posts of an accepted request and hides the posts of strangers', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = createUser();
    $friend = createUser();
    $requester = createUser();
    createFriendship($user, $friend);
    createFriendRequest($requester, $user);

    createPost($friend, ['content' => 'A post from a friend']);
    createPost(createUser(), ['content' => 'A post from a stranger']);
    createPost($requester, ['content' => 'A post from the requester']);

    $testCase->actingAs($user);

    $webpage = visit(route('home', absolute: false))
        ->assertSee('A post from a friend')
        ->assertDontSee('A post from a stranger')
        ->assertDontSee('A post from the requester')
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'home/feed.states.end'));

    $webpage->click('aside li:first-of-type button:has-text("'.translatedTextIn(PreferredLocale::ENGLISH, 'friendship/button.states.accept').'")')
        ->assertSee('A post from the requester')
        ->assertSee('A post from a friend')
        ->assertDontSee('A post from a stranger')
        ->assertPathIs('/home')
        ->assertNoJavaScriptErrors();
});

it('shows the empty feed to a user without friends or posts and links it to the people search', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    createPost(createUser(), ['content' => 'A post from a stranger']);

    $testCase->actingAs(createUser());

    visit(route('home', absolute: false))
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'home/feed.states.empty_title'))
        ->assertDontSee('A post from a stranger')
        ->click('h2:has-text("'.translatedTextIn(PreferredLocale::ENGLISH, 'home/feed.states.empty_title').'") ~ a:has-text("'.translatedTextIn(PreferredLocale::ENGLISH, 'home/feed.states.find_people').'")')
        ->assertPathIs('/friendships/search')
        ->assertNoJavaScriptErrors();
});
