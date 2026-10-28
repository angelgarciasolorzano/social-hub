<?php

declare(strict_types=1);

namespace App\Modules\Admin\Listeners;

use App\Modules\Admin\Providers\AdminServiceProvider;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use Filament\Facades\Filament;

/**
 * Save the language an administrator picks in the panel's switch as their preferred locale, so it
 * follows them to other sessions and devices. It is the same preference the application's account
 * settings edit, and the change is not audited.
 */
class StoreAdminPreferredLocale
{
    public function handle(LocaleChanged $localeChanged): void
    {
        $user = Filament::auth()->user();
        $preferredLocale = PreferredLocale::tryFrom($localeChanged->locale);

        if (! ($user instanceof User)
            || ! \in_array($preferredLocale, AdminServiceProvider::SWITCH_LOCALES, true)
            || $user->preferred_locale === $preferredLocale) {
            return;
        }

        $user->update(['preferred_locale' => $preferredLocale]);
    }
}
