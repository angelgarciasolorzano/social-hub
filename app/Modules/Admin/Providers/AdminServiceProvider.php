<?php

declare(strict_types=1);

namespace App\Modules\Admin\Providers;

use App\Modules\Admin\Console\Commands\AdminBootstrapCommand;
use App\Modules\Admin\Console\Commands\AdminRolePermissionsCommand;
use App\Modules\Admin\Console\Commands\AdminUserRoleCommand;
use App\Modules\Admin\Jobs\RunDatabaseBackup;
use App\Modules\Admin\Listeners\StoreAdminPreferredLocale;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use BezhanSalleh\LanguageSwitch\Enums\Placement;
use BezhanSalleh\LanguageSwitch\Enums\PlacementMode;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * Languages offered by the panel's switch, Spanish first.
     *
     * @var list<PreferredLocale>
     */
    public const array SWITCH_LOCALES = [PreferredLocale::SPANISH, PreferredLocale::ENGLISH];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/invitations.php');

        $this->loadViewsFrom(__DIR__.'/../UI/Views', 'admin');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AdminBootstrapCommand::class,
                AdminRolePermissionsCommand::class,
                AdminUserRoleCommand::class,
            ]);
        }

        $this->registerBackupSchedule();

        $this->configureLanguageSwitch();

        Livewire::addPersistentMiddleware([SwitchLanguageLocale::class]);

        Event::listen(LocaleChanged::class, StoreAdminPreferredLocale::class);
    }

    /**
     * Offer Spanish and English in the panel and on its sign-in, MFA set-up and profile pages. The plugin
     * resolves the locale from the session, the `locale` query parameter, the administrator's saved
     * preference, the browser and finally `app.locale`.
     */
    private function configureLanguageSwitch(): void
    {
        LanguageSwitch::configureUsing(static function (LanguageSwitch $languageSwitch): void {
            $languageSwitch
                ->locales(array_map(
                    static fn (PreferredLocale $preferredLocale): string => $preferredLocale->value,
                    self::SWITCH_LOCALES,
                ))
                ->labels([
                    PreferredLocale::SPANISH->value => 'Español',
                    PreferredLocale::ENGLISH->value => 'English',
                ])
                ->visible(insidePanels: true, outsidePanels: true)
                ->outsidePanelRoutes(static function () use ($languageSwitch): array {
                    $panel = $languageSwitch->getCurrentPanel();

                    return [
                        $panel->generateRouteName('auth.login'),
                        $panel->getSetUpRequiredMultiFactorAuthenticationRouteName(),
                        $panel->generateRouteName('auth.profile'),
                    ];
                })
                ->outsidePanelPlacement(Placement::TopEnd, PlacementMode::Pinned)
                ->userPreferredLocale(static function (): ?string {
                    $user = Filament::auth()->user();

                    return $user instanceof User ? $user->preferred_locale->value : null;
                });
        });
    }

    /** Schedule daily backups and independent health checks during low-traffic hours. */
    private function registerBackupSchedule(): void
    {
        Schedule::job(new RunDatabaseBackup)
            ->dailyAt('01:00')
            ->withoutOverlapping();

        Schedule::command('backup:monitor')
            ->dailyAt('04:30')
            ->withoutOverlapping();
    }
}
