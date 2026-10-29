<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use BezhanSalleh\LanguageSwitch\Http\Livewire\LanguageSwitchComponent;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Create an administrator who can enter the panel, with the given saved language.
 */
function languageSwitchAdmin(PreferredLocale $preferredLocale = PreferredLocale::ENGLISH): User
{
    $user = createUser();
    $user->update(['preferred_locale' => $preferredLocale->value]);
    $user->assignRole(AdminRole::Admin->value);
    $user->saveAppAuthenticationSecret('filament-test-secret');

    return $user;
}

/**
 * The language the panel rendered the page in, read from the `lang` attribute of `<html>`.
 *
 * @param  TestResponse<Response>  $testResponse
 */
function renderedPanelLocale(TestResponse $testResponse): ?string
{
    $content = (string) $testResponse->assertOk()->getContent();

    return preg_match('/<html\s+lang="([^"]+)"/', $content, $matches) === 1 ? $matches[1] : null;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows the switch with Spanish and English inside the panel', function (): void {
    $this->actingAs(languageSwitchAdmin(), 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Español')
        ->assertSee('English');
});

it('opens the panel in the language the administrator saved', function (PreferredLocale $preferredLocale): void {
    $testResponse = $this->actingAs(languageSwitchAdmin($preferredLocale), 'admin')->get('/admin');

    expect(renderedPanelLocale($testResponse))->toBe($preferredLocale->value);
})->with([
    'Spanish' => PreferredLocale::SPANISH,
    'English' => PreferredLocale::ENGLISH,
]);

it('prefers the saved language over the browser language', function (): void {
    $testResponse = $this->actingAs(languageSwitchAdmin(PreferredLocale::ENGLISH), 'admin')
        ->withHeader('Accept-Language', 'es-ES,es;q=0.9')
        ->get('/admin');

    expect(renderedPanelLocale($testResponse))->toBe('en');
});

it('uses the locale query parameter for that request', function (): void {
    $testResponse = $this->actingAs(languageSwitchAdmin(), 'admin')->get('/admin?locale=es');

    expect(renderedPanelLocale($testResponse))->toBe('es');
});

it('keeps the language chosen in the switch on the next requests', function (): void {
    $this->actingAs(languageSwitchAdmin(), 'admin');

    Livewire::test(LanguageSwitchComponent::class)->call('changeLocale', 'es');

    expect(session('locale'))->toBe('es')
        ->and(renderedPanelLocale($this->get('/admin')))->toBe('es');
});

it('falls back to the default language for an unsupported locale, even over the saved one', function (): void {
    $this->actingAs(languageSwitchAdmin(PreferredLocale::SPANISH), 'admin');

    Livewire::test(LanguageSwitchComponent::class)->call('changeLocale', 'fr');

    expect(renderedPanelLocale($this->get('/admin')))->toBe('en');

    session()->forget('locale');

    expect(renderedPanelLocale($this->get('/admin?locale=fr')))->toBe('en')
        ->and(renderedPanelLocale($this->get('/admin')))->toBe('es');
});

it('shows the switch on the sign-in page and translates it', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Español');

    $this->get('/admin/login?locale=es')
        ->assertOk()
        ->assertSee('Entre a su cuenta');
});

it('shows the switch on the MFA set-up and profile pages', function (): void {
    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $this->actingAs($user, 'admin')
        ->get(route('filament.admin.auth.multi-factor-authentication.set-up-required'))
        ->assertOk()
        ->assertSee('Español');

    $user->saveAppAuthenticationSecret('filament-test-secret');

    $this->get(route('filament.admin.auth.profile'))
        ->assertOk()
        ->assertSee('Español');
});

it('does not show the switch on the invitation acceptance page', function (): void {
    ['token' => $token] = createAdminInvitationWithToken();

    $this->get(route('admin.invitations.accept', ['token' => $token]))
        ->assertOk()
        ->assertDontSee('Español');
});

it('keeps the chosen language on Livewire updates of the panel', function (): void {
    expect(Livewire::getPersistentMiddleware())->toContain(SwitchLanguageLocale::class);
});

it('opens the sign-in page in the default language for a browser without an offered language', function (string $acceptLanguage, string $expectedLocale): void {
    $testResponse = $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage])->get('/admin/login');

    expect(renderedPanelLocale($testResponse))->toBe($expectedLocale);
})->with([
    'no language' => ['', 'en'],
    'an unsupported language' => ['fr-FR,fr;q=0.9', 'en'],
    'Spanish' => ['es-MX,es;q=0.9', 'es'],
    'English' => ['en-US,en;q=0.9', 'en'],
]);
