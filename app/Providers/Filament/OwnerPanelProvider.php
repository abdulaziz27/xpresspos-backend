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
            // Log entry to auth gate for debugging
            \Log::info('OwnerPanel auth gate: ENTRY', [
                'url' => request()->fullUrl(),
                'ip' => request()->ip(),
                'is_authenticated' => auth()->check(),
                'user_id' => auth()->id(),
            ]);
            
            // Filament v4 panel-level access gate
            if (!auth()->check()) {
                \Log::warning('OwnerPanel auth gate: User not authenticated', [
                    'url' => request()->fullUrl(),
                    'ip' => request()->ip(),
                ]);
                throw new \App\Exceptions\OwnerPanelAccessDeniedException(
                    'not_authenticated',
                    null,
                    null,
                    []
                );
            }

            $user = auth()->user();
            
            // CRITICAL: Set team context FIRST before any role checks
            // This prevents SQL ambiguity errors in Spatie Permission queries
            $storeId = $user->store_id;
            
            // Try to get from primary store if not directly set
            if (!$storeId) {
                $primaryStore = $user->primaryStore();
                $storeId = $primaryStore?->id;
            }
            
            // Set team context immediately if we have a store
            if ($storeId) {
                setPermissionsTeamId($storeId);
            }
            
            // Get roles with team context already set to avoid SQL ambiguity
            $userRoles = [];
            try {
                $userRoles = $user->getRoleNames()->toArray();
            } catch (\Exception $e) {
                // Fallback: get roles directly from relationship
                \Log::warning('OwnerPanel auth gate: Error getting role names, using fallback', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'store_id' => $storeId,
                ]);
                if ($storeId) {
                    $roles = $user->roles()->get();
                    $userRoles = $roles->pluck('name')->toArray();
                }
            }

            // Check if email is verified
            if (!$user->email_verified_at) {
                \Log::warning('OwnerPanel auth gate: Email not verified', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
                throw new \App\Exceptions\OwnerPanelAccessDeniedException(
                    'email_not_verified',
                    $user->email,
                    null,
                    $userRoles
                );
            }

            // Allow admin_sistem and super_admin to access owner panel for monitoring/troubleshooting
            // These are internal roles that need to see the customer perspective
            // Check these roles WITHOUT team context (they are global roles)
            if ($user->hasRole(['admin_sistem', 'super_admin'])) {
                \Log::info('OwnerPanel auth gate: Admin system access granted', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'roles' => $userRoles,
                ]);
                return true;
            }

            // If no store found, deny access with informative message
            if (!$storeId) {
                \Log::warning('OwnerPanel auth gate: User has no store', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'roles' => $userRoles,
                ]);
                throw new \App\Exceptions\OwnerPanelAccessDeniedException(
                    'no_store',
                    $user->email,
                    null,
                    $userRoles
                );
            }

            // Check if store is active
            $store = \App\Models\Store::find($storeId);
            if ($store && $store->status !== 'active') {
                \Log::warning('OwnerPanel auth gate: Store is not active', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'store_id' => $storeId,
                    'store_status' => $store->status,
                ]);
                throw new \App\Exceptions\OwnerPanelAccessDeniedException(
                    'store_inactive',
                    $user->email,
                    $storeId,
                    $userRoles
                );
            }

            // Team context is already set above, now check if user has owner role
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
                'roles' => $userRoles,
            ]);

            // If user doesn't have owner role or assignment, provide detailed error
            if (!$hasOwnerRole && !$hasOwnerAssignment) {
                // Determine the most specific reason
                $reason = !$hasOwnerRole && !$hasOwnerAssignment 
                    ? 'no_owner_role'  // Primary reason: missing owner role
                    : (!$hasOwnerAssignment ? 'no_owner_assignment' : 'no_owner_role');
                
                \Log::warning('OwnerPanel auth gate: Access denied', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'store_id' => $storeId,
                    'has_owner_role' => $hasOwnerRole,
                    'has_owner_assignment' => $hasOwnerAssignment,
                    'roles' => $userRoles,
                    'reason' => $reason,
                ]);

                throw new \App\Exceptions\OwnerPanelAccessDeniedException(
                    $reason,
                    $user->email,
                    $storeId,
                    $userRoles
                );
            }

            return true;
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
