<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Providers\AuthServiceProvider;
use App\Services\StoreContext;
use App\Services\Ai\Clients\AiClientInterface;
use App\Services\Ai\Clients\DummyAiClient;
use App\Services\Ai\Clients\GeminiAiClient;
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

        // Bind AI Client Interface
        $this->app->bind(AiClientInterface::class, function () {
            $provider = config('ai.provider', 'dummy');
            $enabled = config('ai.enabled', false);

            if (!$enabled) {
                return new DummyAiClient();
            }

            return match ($provider) {
                'gemini' => new GeminiAiClient(),
                'openai' => throw new \RuntimeException('OpenAI client not yet implemented'),
                default => new DummyAiClient(),
            };
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
