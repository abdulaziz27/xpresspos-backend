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
            ->brandName('POS Xpress Toko')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('img/logo-xpress.png'))
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Green,
            ])
            ->navigationGroups([
                // Order from most important (top) to least (bottom)
                'Operasional Harian',
                'Produk & Inventori',
                'Pelanggan & Loyalti',
                'Promo & Retur',
                'Keuangan & Laporan',
                'Toko & Tim',
                'Langganan & Billing',
            ])
            ->discoverResources(in: app_path('Filament/Owner/Resources'), for: 'App\\Filament\\Owner\\Resources')
            ->discoverPages(in: app_path('Filament/Owner/Pages'), for: 'App\\Filament\\Owner\\Pages')
            ->pages([
                \App\Filament\Owner\Pages\OwnerDashboard::class,
            ])
            ->widgets([
                \App\Filament\Owner\Widgets\SubscriptionDashboardWidget::class, // status subscription ringkas (v4 simplified) - dipindah ke paling atas
                \App\Filament\Owner\Widgets\OwnerStatsWidget::class, // ringkasan transaksi & pendapatan (dengan filter)
                \App\Filament\Owner\Widgets\ProfitAnalysisWidget::class, // laba kotor & bersih (dengan filter)
                \App\Filament\Owner\Widgets\SalesRevenueChartWidget::class, // grafik total pendapatan (bar)
                \App\Filament\Owner\Widgets\TopMenuTableWidget::class, // menu terlaris (tabel)
                \App\Filament\Owner\Widgets\BestBranchesWidget::class, // cabang dengan penjualan terbaik (tabel)
                \App\Filament\Owner\Widgets\LowStockWidget::class, // stok bahan baku menipis
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
                \App\Http\Middleware\LogSecurityEvents::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            ->authPasswordBroker('users');

        // Configure domain/path BEFORE auth() callback
        if ($this->shouldUseDomain($ownerDomain)) {
            $panel->domain($ownerDomain)->path('/');
        } else {
            $panel->path('owner-panel');
        }

        // Auth gate must be LAST in the chain
        $panel->auth(function () {
            // Filament v4 panel-level access gate
            if (!auth()->check()) {
                return false;
            }

            $user = auth()->user();

            // CRITICAL: Always ensure team context is set before role checks
            // Cannot rely on middleware order - explicitly set here
            $storeId = $user->store_id;
            
            // Try to get from primary store if not directly set
            if (!$storeId) {
                $primaryStore = $user->primaryStore();
                $storeId = $primaryStore?->id;
            }

            // If no store found, deny access immediately
            if (!$storeId) {
                \Log::warning('OwnerPanel auth gate: User has no store', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
                return false;
            }

            // Set team context BEFORE any role check
            setPermissionsTeamId($storeId);

            // Now check if user has owner role (will use correct team context)
            $hasOwnerRole = $user->hasRole('owner');
            
            // Also check store assignments as fallback
            $hasOwnerAssignment = $user->storeAssignments()
                ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
                ->exists();

            \Log::info('OwnerPanel auth gate', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'store_id' => $storeId,
                'team_context_set' => getPermissionsTeamId(),
                'has_owner_role' => $hasOwnerRole,
                'has_owner_assignment' => $hasOwnerAssignment,
                'roles' => $user->getRoleNames()->toArray(),
            ]);

            return $hasOwnerRole || $hasOwnerAssignment;
        });

        return $panel;
    }

    protected function shouldUseDomain(?string $domain): bool
    {
        if (blank($domain)) {
            \Log::warning('OwnerPanelProvider: Domain is blank', [
                'env_owner_domain' => env('OWNER_DOMAIN'),
            ]);
            return false;
        }

        $isProduction = app()->environment('production');
        $hasLocalhost = \Illuminate\Support\Str::contains($domain, ['localhost', '127.0.0.1']);
        $shouldUse = $isProduction && !$hasLocalhost;

        \Log::info('OwnerPanelProvider: shouldUseDomain check', [
            'domain' => $domain,
            'is_production' => $isProduction,
            'app_env' => app()->environment(),
            'has_localhost' => $hasLocalhost,
            'should_use_domain' => $shouldUse,
        ]);

        // Only use domain routing in production environment
        return $shouldUse;
    }
}
