<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class CheckFilamentRoutes extends Command
{
    protected $signature = 'filament:check-routes';
    protected $description = 'Check if Filament routes are properly registered';

    public function handle(): int
    {
        $this->info('🔍 Checking Filament routes registration...');
        $this->newLine();

        // Check environment
        $this->info('📌 Environment Info:');
        $this->line('   APP_ENV: ' . app()->environment());
        $this->line('   APP_DEBUG: ' . (config('app.debug') ? 'true' : 'false'));
        $this->line('   OWNER_URL: ' . config('app.owner_url'));
        $this->line('   ADMIN_URL: ' . config('app.admin_url'));
        $this->newLine();

        // Check Owner Panel Provider
        $defaultOwnerUrl = rtrim(config('app.url'), '/') . '/owner';
        $ownerPath = trim(parse_url(config('app.owner_url', $defaultOwnerUrl), PHP_URL_PATH) ?: 'owner', '/');
        $this->info("🏪 Owner Panel Path: /{$ownerPath}");
        
        // Check route registration
        $ownerLoginRoute = null;
        try {
            $ownerLoginRoute = Route::getRoutes()->getByName('filament.owner.auth.login');
        } catch (\Exception $e) {
            // Route not found
        }

        if ($ownerLoginRoute) {
            $this->info('   ✅ Route found: filament.owner.auth.login');
        $this->line('   Domain: ' . ($ownerLoginRoute->domain() ?? 'none (path-based)'));
            $this->line('   URI: ' . $ownerLoginRoute->uri());
            $this->line('   Methods: ' . implode(', ', $ownerLoginRoute->methods()));
        } else {
            $this->error('   ❌ Route NOT found: filament.owner.auth.login');
        }

        $this->newLine();

        // Check Admin Panel Provider
        $defaultAdminUrl = rtrim(config('app.url'), '/') . '/admin';
        $adminPath = trim(parse_url(config('app.admin_url', $defaultAdminUrl), PHP_URL_PATH) ?: 'admin', '/');
        $this->info("🔧 Admin Panel Path: /{$adminPath}");
        
        $adminLoginRoute = null;
        try {
            $adminLoginRoute = Route::getRoutes()->getByName('filament.admin.auth.login');
        } catch (\Exception $e) {
            // Route not found
        }

        if ($adminLoginRoute) {
            $this->info('   ✅ Route found: filament.admin.auth.login');
        $this->line('   Domain: ' . ($adminLoginRoute->domain() ?? 'none (path-based)'));
            $this->line('   URI: ' . $adminLoginRoute->uri());
            $this->line('   Methods: ' . implode(', ', $adminLoginRoute->methods()));
        } else {
            $this->error('   ❌ Route NOT found: filament.admin.auth.login');
        }

        $this->newLine();

        // List all Filament routes
        $this->info('📋 All Filament Routes:');
        $filamentRoutes = collect(Route::getRoutes())->filter(function ($route) {
            return str_contains($route->getName() ?? '', 'filament');
        });

        if ($filamentRoutes->isEmpty()) {
            $this->error('   ❌ No Filament routes found at all!');
            $this->warn('   This means Filament panels are not registered.');
        } else {
            $this->info('   Found ' . $filamentRoutes->count() . ' Filament routes:');
            foreach ($filamentRoutes->take(10) as $route) {
                $name = $route->getName() ?? 'unnamed';
                $domain = $route->domain() ?? 'no domain';
                $this->line("   - {$name} (domain: {$domain})");
            }
            if ($filamentRoutes->count() > 10) {
                $this->line('   ... and ' . ($filamentRoutes->count() - 10) . ' more');
            }
        }

        $this->newLine();

        // Check provider registration
        $this->info('🔌 Provider Registration:');
        $providers = config('app.providers', []);
        $ownerProviderFound = in_array(
            \App\Providers\Filament\OwnerPanelProvider::class,
            $providers
        );
        $adminProviderFound = in_array(
            \App\Providers\Filament\AdminPanelProvider::class,
            $providers
        );

        if ($ownerProviderFound) {
            $this->info('   ✅ OwnerPanelProvider registered');
        } else {
            $this->error('   ❌ OwnerPanelProvider NOT registered in config/app.php');
        }

        if ($adminProviderFound) {
            $this->info('   ✅ AdminPanelProvider registered');
        } else {
            $this->error('   ❌ AdminPanelProvider NOT registered in config/app.php');
        }

        $this->newLine();

        // Recommendations
        if (!$ownerLoginRoute || !$adminLoginRoute) {
            $this->warn('⚠️  RECOMMENDATIONS:');
            $this->line('   1. Clear all caches:');
            $this->line('      php artisan optimize:clear');
            $this->line('      php artisan route:clear');
            $this->line('      php artisan config:clear');
            $this->newLine();
            $this->line('   2. Pastikan konfigurasi URL panel sudah benar:');
            $this->line('      OWNER_URL=' . config('app.owner_url'));
            $this->line('      ADMIN_URL=' . config('app.admin_url'));
            $this->newLine();
            $this->line('   3. Run Filament upgrade:');
            $this->line('      php artisan filament:upgrade');
            $this->newLine();
            $this->line('   4. Check bootstrap/providers.php includes:');
            $this->line('      App\\Providers\\Filament\\OwnerPanelProvider::class');
            $this->line('      App\\Providers\\Filament\\AdminPanelProvider::class');
        } else {
            $this->info('✅ All Filament routes are properly registered!');
        }

        return 0;
    }
}

