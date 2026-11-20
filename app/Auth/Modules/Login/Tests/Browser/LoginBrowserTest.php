<?php

declare(strict_types=1);

use App\User\Enums\PreferredLocale;

it('renders the login form in a browser', function (): void {
    visit(route('login', absolute: false))
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors()
        ->assertVisible('input[name="email"]')
        ->assertVisible('input[name="password"]')
        ->assertVisible('button[data-test="login-button"]');
});

it('signs in with the form and lands on the home page without JavaScript errors', function (): void {
    $user = createUser();

    visit(route('login', absolute: false))
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('button[data-test="login-button"]')
        ->assertPathIs('/home')
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'home/sidebar.friend_requests.title'))
        ->assertNoJavaScriptErrors();
});
