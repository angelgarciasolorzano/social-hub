<?php

declare(strict_types=1);

namespace App\Modules\Admin\Providers;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Modules\Admin\Filament\Widgets\AdminQuickLinksWidget;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->login()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->codeWindow(1)
                    ->brandName(config()->string('app.name').' Admin'),
            ], isRequired: true)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Modules/Admin/Filament/Resources'), for: 'App\Modules\Admin\Filament\Resources')
            ->discoverPages(in: app_path('Modules/Admin/Filament/Pages'), for: 'App\Modules\Admin\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Modules/Admin/Filament/Widgets'), for: 'App\Modules\Admin\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                AdminQuickLinksWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                EnsureAccountIsActive::class.':admin,filament.admin.auth.login',
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
