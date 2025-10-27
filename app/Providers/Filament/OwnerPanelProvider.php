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
        $ownerDomain = env('OWNER_DOMAIN', 'dashboard.xpresspos.id');

        $panel = $panel
            ->id('owner')
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
                'Billing' => 'heroicon-o-credit-card',
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
                \App\Filament\Owner\Widgets\SubscriptionDashboardWidget::class,
                \App\Filament\Owner\Widgets\PaymentAnalyticsWidget::class,
                \App\Filament\Owner\Widgets\PaymentMethodBreakdownWidget::class,
                \App\Filament\Owner\Widgets\AdvancedAnalyticsWidget::class,
                \App\Filament\Owner\Widgets\CogsSummaryWidget::class,
                \App\Filament\Owner\Widgets\ProfitAnalysisWidget::class,
                \App\Filament\Owner\Widgets\BusinessRecommendationsWidget::class,
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
                \App\Http\Middleware\EnsureFilamentTeamContext::class,
                FilamentRoleMiddleware::class . ':owner',
                \App\Http\Middleware\LogSecurityEvents::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            ->authPasswordBroker('users');

        if ($this->shouldUseDomain($ownerDomain)) {
            $panel->domain($ownerDomain)->path('/');
        } else {
            $panel->path('owner-panel');
        }

        return $panel;
    }

    protected function shouldUseDomain(?string $domain): bool
    {
        if (blank($domain)) {
            return false;
        }

        // Only use domain routing in production environment
        return app()->environment('production') && ! \Illuminate\Support\Str::contains($domain, ['localhost', '127.0.0.1']);
    }
}
