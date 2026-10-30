<?php

declare(strict_types=1);

use App\User\Enums\PreferredLocale;
use Inertia\Testing\AssertableInertia as Assert;

const ACCOUNT_SETTINGS_COMPONENT = 'setting/modules/accountSettings/AccountSettings';

it('shares the active language and the dates catalog in that language', function (PreferredLocale $preferredLocale, string $never): void {
    $this->actingAs(createUserPreferring($preferredLocale))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('locale', $preferredLocale->value)
            ->where('translations.dates.never', $never));
})->with([
    'English' => [PreferredLocale::ENGLISH, 'Never'],
    'Spanish' => [PreferredLocale::SPANISH, 'Nunca'],
]);

it('renders the html lang attribute in the active language', function (): void {
    $this->actingAs(createUserPreferring(PreferredLocale::SPANISH))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSeeHtml('<html lang="es"');
});

it('does not resend the translations on a partial reload that asks for another prop', function (): void {
    $this->actingAs(createUserPreferring(PreferredLocale::SPANISH));

    $this->withHeaders(inertiaPartialHeaders($this, ACCOUNT_SETTINGS_COMPONENT, 'accountSettings'))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertJsonMissingPath('props.translations')
        ->assertJsonPath('props.accountSettings.preferredLocale', 'es');
});
