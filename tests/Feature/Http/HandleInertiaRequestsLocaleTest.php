<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

const TRUSTED_DEVICE_COMPONENT = 'setting/modules/trustedDevice/TrustedDevice';

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

it('keeps localized activity and shared props aligned after a language change on partial reload', function (): void {
    $user = createUserPreferring(PreferredLocale::SPANISH);
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Created]);

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component(TRUSTED_DEVICE_COMPONENT)
            ->where('locale', PreferredLocale::SPANISH->value)
            ->where('translations.dates.never', 'Nunca'));

    User::query()
        ->findOrFail($user->id)
        ->forceFill(['preferred_locale' => PreferredLocale::ENGLISH])
        ->save();

    $this->actingAs(User::query()->findOrFail($user->id))
        ->withHeaders(inertiaPartialHeaders($this, TRUSTED_DEVICE_COMPONENT, 'activityDialog'))
        ->get(route('setting.security.trusted-devices.index'))
        ->assertOk()
        ->assertJsonPath('props.locale', PreferredLocale::ENGLISH->value)
        ->assertJsonPath('props.translations.dates.never', 'Never')
        ->assertJsonPath(
            'props.activityDialog.activityLog.data.0.actionLabel',
            translatedTextIn(PreferredLocale::ENGLISH, 'setting/trusted-devices.actions.created'),
        );
});
