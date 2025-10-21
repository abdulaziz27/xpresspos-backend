<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Providers\AuthServiceProvider;
use App\Services\StoreContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StoreContext::class, function ($app) {
            $session = $app->bound('session') ? $app->make('session') : null;

            return new StoreContext($session);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Order::observe(OrderObserver::class);

        // Ensure authentication policies are loaded for API authorization checks
        $this->app->register(AuthServiceProvider::class);
    }
}
