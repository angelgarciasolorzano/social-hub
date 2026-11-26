<?php

declare(strict_types=1);

use App\User\Enums\PreferredLocale;

it('shows the QR code after enabling two-factor authentication right after signing in', function (): void {
    $user = createUser();

    visit(route('login', absolute: false))
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('button[data-test="login-button"]')
        ->assertPathIs('/home')
        ->navigate(route('profile.edit', absolute: false))
        ->click(translatedTextIn(PreferredLocale::ENGLISH, 'setting/common.sidebar.security'))
        ->click(translatedTextIn(PreferredLocale::ENGLISH, 'setting/common.sidebar.two_factor'))
        ->fill('password', 'password')
        ->click('button[data-test="confirm-password-button"]')
        ->assertPathIs('/setting/two-factor-authentication')
        ->click(translatedTextIn(PreferredLocale::ENGLISH, 'setting/two-factor.disabled_view.activate'))
        ->assertVisible('[role=dialog] svg[viewBox="0 0 192 192"]')
        ->assertDontSee(translatedTextIn(PreferredLocale::ENGLISH, 'setting/two-factor.error_alert.title'))
        ->assertNoJavaScriptErrors();
});
