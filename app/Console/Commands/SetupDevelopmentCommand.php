<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupDevelopmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:setup {--force : Force overwrite existing .env file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup development environment with proper configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Setting up XpressPOS development environment...');

        // Check if .env exists
        if (File::exists('.env') && !$this->option('force')) {
            if (!$this->confirm('⚠️  .env file already exists. Do you want to continue? (This will update existing values)')) {
                $this->info('Setup cancelled.');
                return;
            }
        }

        // Copy .env.example to .env if it doesn't exist
        if (!File::exists('.env')) {
            File::copy('.env.example', '.env');
            $this->info('✅ Created .env file from .env.example');
        }

        // Generate app key if not set
        if (empty(env('APP_KEY'))) {
            $this->call('key:generate');
            $this->info('✅ Generated application key');
        }

        // Set development-specific values
        $this->updateEnvFile([
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://localhost:8000',
            
            // Database
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'xpresspos_dev',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            
            // Xendit Development
            'XENDIT_API_KEY' => 'xnd_development_dummy_key_for_local_testing',
            'XENDIT_WEBHOOK_TOKEN' => 'dummy_webhook_token_for_local_testing',
            'XENDIT_IS_PRODUCTION' => 'false',
            
            // Mail
            'MAIL_MAILER' => 'log',
            
            // Queue
            'QUEUE_CONNECTION' => 'database',
        ]);

        $this->info('✅ Updated .env with development configuration');

        // Run migrations
        if ($this->confirm('🗄️  Do you want to run database migrations?')) {
            $this->call('migrate');
            $this->info('✅ Database migrations completed');
        }

        // Seed database
        if ($this->confirm('🌱 Do you want to seed the database with sample data?')) {
            $this->call('db:seed');
            $this->info('✅ Database seeding completed');
        }

        $this->newLine();
        $this->info('🎉 Development environment setup completed!');
        $this->newLine();
        
        $this->line('📋 <comment>Next steps:</comment>');
        $this->line('   1. Start the development server: <info>php artisan serve</info>');
        $this->line('   2. Visit the landing page: <info>http://localhost:8000</info>');
        $this->line('   3. Test the payment flow: <info>http://localhost:8000/pricing</info>');
        $this->newLine();
        
        $this->line('💡 <comment>Development Notes:</comment>');
        $this->line('   • Xendit is in dummy mode (no real payments)');
        $this->line('   • All emails are logged to storage/logs/laravel.log');
        $this->line('   • Payment flow will use mock responses');
        $this->newLine();
    }

    /**
     * Update .env file with new values
     */
    private function updateEnvFile(array $values): void
    {
        $envFile = File::get('.env');

        foreach ($values as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $envFile)) {
                $envFile = preg_replace($pattern, $replacement, $envFile);
            } else {
                $envFile .= "\n{$replacement}";
            }
        }

        File::put('.env', $envFile);
    }
}
