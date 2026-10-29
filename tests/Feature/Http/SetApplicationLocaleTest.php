<?php

declare(strict_types=1);

use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Support\Facades\Route;

const LOCALE_PROBE_URI = '/locale-probe';

function userPreferring(PreferredLocale $preferredLocale): User
{
    $user = createUser();
    $user->forceFill(['preferred_locale' => $preferredLocale])->save();

    return $user;
}

beforeEach(function (): void {
    Route::middleware('web')->get(LOCALE_PROBE_URI, fn (): string => app()->getLocale());
});

it('applies the preferred language of the signed-in user over the browser one', function (PreferredLocale $preferredLocale, string $acceptLanguage): void {
    $this->actingAs(userPreferring($preferredLocale))
        ->withHeader('Accept-Language', $acceptLanguage)
        ->get(LOCALE_PROBE_URI)
        ->assertOk()
        ->assertContent($preferredLocale->value);
})->with([
    'Spanish user, English browser' => [PreferredLocale::SPANISH, 'en-US,en;q=0.9'],
    'English user, Spanish browser' => [PreferredLocale::ENGLISH, 'es-MX,es;q=0.9'],
]);

it('applies the first supported browser language to a guest', function (string $acceptLanguage, string $expectedLocale): void {
    $this->withHeader('Accept-Language', $acceptLanguage)
        ->get(LOCALE_PROBE_URI)
        ->assertOk()
        ->assertContent($expectedLocale);
})->with([
    'English' => ['en-US,en;q=0.9', 'en'],
    'Spanish' => ['es-MX', 'es'],
    'unsupported first, English later' => ['fr-FR,fr;q=0.9,en;q=0.5', 'en'],
]);

/*
 * Symfony gives every test request `Accept-Language: en-us,en;q=0.5`, so an empty header stands for a
 * browser that sends none.
 */
it('gives Spanish to a guest whose browser asks for no supported language', function (string $acceptLanguage): void {
    $this->withHeader('Accept-Language', $acceptLanguage)
        ->get(LOCALE_PROBE_URI)
        ->assertOk()
        ->assertContent('es');
})->with([
    'no language' => [''],
    'French only' => ['fr-FR,fr;q=0.9'],
]);

it('treats a user with an unsupported stored language as a guest', function (): void {
    $user = createUser();
    User::query()->whereKey($user->id)->update(['preferred_locale' => 'fr']);

    $this->actingAs(User::query()->findOrFail($user->id))
        ->withHeader('Accept-Language', 'en-US')
        ->get(LOCALE_PROBE_URI)
        ->assertOk()
        ->assertContent('en');
});
