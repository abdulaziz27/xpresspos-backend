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
use Illuminate\Support\Str;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $adminDomain = config('domains.admin');

        $panel = $panel
            ->id('admin')
            ->login()
            ->brandName('POS Xpress Admin')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('img/logo-xpress.png'))
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                'System Management' => 'heroicon-o-cog-6-tooth',
                'Store Management' => 'heroicon-o-building-storefront',
                'User Management' => 'heroicon-o-users',
                'Subscription Management' => 'heroicon-o-currency-dollar',
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                \App\Filament\Admin\Widgets\AdminStatsWidget::class,
                \App\Filament\Admin\Widgets\SystemHealthWidget::class,
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
                FilamentRoleMiddleware::class . ':admin_sistem',
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->default();

        if ($this->shouldUseDomain($adminDomain)) {
            $panel->domain($adminDomain)->path('/');
        } else {
            $panel->path('admin');
        }

        return $panel;
    }

    protected function shouldUseDomain(?string $domain): bool
    {
        if (blank($domain)) {
            return false;
        }

        return ! Str::contains($domain, ['localhost', '127.0.0.1']);
    }
}
