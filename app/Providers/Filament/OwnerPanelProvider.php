<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentRoleMiddleware;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\EnsureStoreContext;

class OwnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('owner')
            ->path('owner')
            ->login()
            ->brandName('POS Xpress Store')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('img/logo-xpress.png'))
            ->colors([
                'primary' => Color::Green,
            ])
            ->navigationGroups([
                'Product Management' => 'heroicon-o-cube',
                'Order Management' => 'heroicon-o-shopping-cart',
                'Customer Management' => 'heroicon-o-users',
                'Financial Management' => 'heroicon-o-banknotes',
                'Inventory Management' => 'heroicon-o-arrow-path',
                'Store Operations' => 'heroicon-o-building-storefront',
            ])
            ->discoverResources(in: app_path('Filament/Owner/Resources'), for: 'App\\Filament\\Owner\\Resources')
            ->discoverPages(in: app_path('Filament/Owner/Pages'), for: 'App\\Filament\\Owner\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Owner/Widgets'), for: 'App\\Filament\\Owner\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                \App\Filament\Owner\Widgets\OwnerStatsWidget::class,
                \App\Filament\Owner\Widgets\CogsSummaryWidget::class,
                \App\Filament\Owner\Widgets\RecentOrdersWidget::class,
                \App\Filament\Owner\Widgets\LowStockWidget::class,
                \App\Filament\Owner\Widgets\RecipePerformanceWidget::class,
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
                EnsureStoreContext::class,
                FilamentRoleMiddleware::class . ':owner',
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            ->authPasswordBroker('users');
    }
}
