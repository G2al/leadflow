<?php

namespace App\Providers\Filament;

use App\Enums\NavigationGroup;
use App\Http\Controllers\OfferTestLandingController;
use App\Http\Middleware\DenyFraming;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('LeadFlow')
            ->sidebarWidth('15rem')
            ->navigationGroups(NavigationGroup::class)
            ->registerErrorNotification(
                title: 'Elemento non più disponibile',
                body: 'È stato eliminato da un altro utente. Torna alla lista per continuare.',
                statusCode: 404,
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                DenyFraming::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ], isPersistent: true)
            ->authenticatedRoutes(function (): void {
                Route::get('offers/{offer}/test-landing', OfferTestLandingController::class)
                    ->middleware('can:view,offer')
                    ->name('offers.test-landing');
            });
    }
}
