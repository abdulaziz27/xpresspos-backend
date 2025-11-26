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
use App\Http\Middleware\EnsureTenantHasActivePlan;

class OwnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('owner')
            ->login()
            ->brandName('POS Xpress Toko')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('logo/logo-4-(ori).svg'))
            ->sidebarCollapsibleOnDesktop()
            ->darkMode(false) // Set default theme to light mode
            ->colors([
                'primary' => Color::Green,
            ])
            ->navigationGroups([
                'Dashboard',
                'Operasional Harian',
                'Produk',
                'Inventori',
                'Promo & Kampanye',
                'Member & Loyalty',
                'Keuangan & Laporan',
                'Logs & Audit',
                'Toko & Tim',
                'Langganan & Billing',
                'AI',
                'Pengaturan',
            ])
            ->discoverResources(in: app_path('Filament/Owner/Resources'), for: 'App\\Filament\\Owner\\Resources')
            ->discoverPages(in: app_path('Filament/Owner/Pages'), for: 'App\\Filament\\Owner\\Pages')
            ->pages([
                \App\Filament\Owner\Pages\OwnerDashboard::class,
            ])
            ->widgets([
                // Dashboard Widgets
                // \App\Filament\Owner\Widgets\UpgradeBannerWidget::class, // DISABLED - upgrade banner
                \App\Filament\Owner\Widgets\SubscriptionDashboardWidget::class, // status subscription ringkas (v4 simplified)
                \App\Filament\Owner\Widgets\OwnerStatsWidget::class, // ringkasan transaksi & pendapatan (dengan filter)
                \App\Filament\Owner\Widgets\SalesRevenueChartWidget::class, // grafik total pendapatan (bar)
                \App\Filament\Owner\Widgets\LowStockWidget::class, // stok bahan baku menipis
                \App\Filament\Owner\Widgets\TopMenuTableWidget::class, // menu terlaris (tabel)
                \App\Filament\Owner\Widgets\BestBranchesWidget::class, // cabang dengan penjualan terbaik (tabel)
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
                EnsureTenantHasActivePlan::class,
                \App\Http\Middleware\EnsureFilamentTeamContext::class,
                \App\Http\Middleware\LogSecurityEvents::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->path('owner');

        return $panel;
    }
}
