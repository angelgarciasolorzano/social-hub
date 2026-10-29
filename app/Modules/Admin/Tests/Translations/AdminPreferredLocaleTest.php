<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use App\User\Events\AccountUpdated;
use App\User\Models\User;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use BezhanSalleh\LanguageSwitch\Http\Livewire\LanguageSwitchComponent;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Create an administrator who can enter the panel, with the given saved language.
 */
function preferredLocaleAdmin(PreferredLocale $preferredLocale = PreferredLocale::ENGLISH): User
{
    $user = createUser();
    $user->update(['preferred_locale' => $preferredLocale->value]);
    $user->assignRole(AdminRole::Admin->value);
    $user->saveAppAuthenticationSecret('filament-test-secret');

    return $user;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('saves the language chosen in the switch as the administrator preference', function (): void {
    $user = preferredLocaleAdmin();

    $this->actingAs($user, 'admin');

    Livewire::test(LanguageSwitchComponent::class)->call('changeLocale', 'es');

    expect($user->refresh()->preferred_locale)->toBe(PreferredLocale::SPANISH);

    session()->flush();

    $this->get('/admin')
        ->assertOk()
        ->assertSeeHtml('lang="es"');
});

it('ignores a locale the panel does not offer', function (): void {
    $user = preferredLocaleAdmin(PreferredLocale::SPANISH);

    $this->actingAs($user, 'admin');

    Livewire::test(LanguageSwitchComponent::class)->call('changeLocale', 'fr');

    expect($user->refresh()->preferred_locale)->toBe(PreferredLocale::SPANISH);
});

it('ignores the change without an administrator signed in to the panel', function (): void {
    $user = preferredLocaleAdmin();

    $this->actingAs($user, 'web');

    event(new LocaleChanged('es'));

    expect($user->refresh()->preferred_locale)->toBe(PreferredLocale::ENGLISH);
});

it('does not write when the language is already the saved one', function (): void {
    $user = preferredLocaleAdmin(PreferredLocale::SPANISH);
    $updatedAt = $user->refresh()->updated_at;

    $this->travel(5)->minutes();
    $this->actingAs($user, 'admin');

    event(new LocaleChanged('es'));

    expect($user->refresh()->updated_at)->toEqual($updatedAt);
});

it('does not audit the language change', function (): void {
    Event::fake([AccountUpdated::class]);

    $user = preferredLocaleAdmin();
    $activityCount = ActivityLog::query()->count();

    $this->actingAs($user, 'admin');

    event(new LocaleChanged('es'));

    expect($user->refresh()->preferred_locale)->toBe(PreferredLocale::SPANISH)
        ->and(ActivityLog::query()->count())->toBe($activityCount);

    Event::assertNotDispatched(AccountUpdated::class);
});
