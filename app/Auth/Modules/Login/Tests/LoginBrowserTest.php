<?php

declare(strict_types=1);

it('renders the login form in a browser', function (): void {
    visit(route('login', absolute: false))
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors()
        ->assertVisible('input[name="email"]')
        ->assertVisible('input[name="password"]')
        ->assertVisible('button[data-test="login-button"]');
});
