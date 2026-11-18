<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;

it('renders the login form in a browser', function (): void {
    $this->withVite();

    Vite::useHotFile(storage_path('framework/testing-vite.hot'));

    visit(route('login', absolute: false))
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors()
        ->assertVisible('input[name="email"]')
        ->assertVisible('input[name="password"]')
        ->assertVisible('button[data-test="login-button"]');
});
