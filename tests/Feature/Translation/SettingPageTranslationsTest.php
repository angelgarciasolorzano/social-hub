<?php

declare(strict_types=1);

use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Visit a settings page as the given user, with the password already confirmed for the two-factor page.
 *
 * @return TestResponse<Response>
 */
function visitSettingPage(TestCase $testCase, User $user, string $routeName): TestResponse
{
    return $testCase->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route($routeName));
}

dataset('setting pages', [
    'Profile' => ['profile.edit', ['setting/common', 'setting/account-settings']],
    'Two-factor authentication' => [
        'setting.security.two-factor-authentication.index',
        ['setting/common', 'setting/two-factor', 'setting/trusted-devices', 'setting/trusted-device-dialogs'],
    ],
    'Trusted devices' => [
        'setting.security.trusted-devices.index',
        ['setting/common', 'setting/trusted-devices', 'setting/trusted-device-dialogs'],
    ],
    'Appearance' => ['preference.appearance', ['setting/common']],
]);

dataset('setting languages', [
    'English' => [PreferredLocale::ENGLISH, 'Profile'],
    'Spanish' => [PreferredLocale::SPANISH, 'Perfil'],
]);

it('shares exactly the translation groups of each settings page', function (string $routeName, array $groups, PreferredLocale $preferredLocale): void {
    $translations = visitSettingPage($this, createUserPreferring($preferredLocale), $routeName)
        ->assertOk()
        ->inertiaProps('translations');

    expect($translations)->toBeArray()
        ->and(array_keys($translations))->toEqualCanonicalizing([...$groups, 'dates']);
})->with('setting pages')->with([PreferredLocale::ENGLISH, PreferredLocale::SPANISH]);

it('shares each settings page in the language of the user', function (string $routeName, array $groups, PreferredLocale $preferredLocale, string $profileLabel): void {
    visitSettingPage($this, createUserPreferring($preferredLocale), $routeName)
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('locale', $preferredLocale->value)
            ->where('translations.setting/common.sidebar.profile', $profileLabel));
})->with('setting pages')->with('setting languages');

it('shows the next page in the language just chosen on the profile', function (): void {
    $user = createUserPreferring(PreferredLocale::SPANISH);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), ['name' => $user->name, 'preferredLocale' => PreferredLocale::ENGLISH->value])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('message', translatedTextIn(PreferredLocale::ENGLISH, 'setting/account-settings.messages.profile_updated'));

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('locale', PreferredLocale::ENGLISH->value)
            ->where('translations.setting/account-settings.page_title', 'Account settings'));
});

it('sends a guest who asks for a settings page to the login', function (): void {
    $this->get(route('profile.edit'))
        ->assertRedirect(route('login'));
});
