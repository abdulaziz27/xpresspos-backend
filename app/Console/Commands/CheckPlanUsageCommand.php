<?php

namespace App\Console\Commands;

use App\Services\PlanUsageNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPlanUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plan:check-usage 
                            {--tenant= : Check usage for specific tenant ID}
                            {--quiet : Suppress output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check plan usage for all tenants and send email notifications if usage reaches 80% or 100%';

    /**
     * Execute the console command.
     */
    public function handle(PlanUsageNotificationService $notificationService): int
    {
        $this->info('Checking plan usage for tenants...');

        try {
            if ($tenantId = $this->option('tenant')) {
                $tenant = \App\Models\Tenant::with('plan')->find($tenantId);
                
                if (!$tenant) {
                    $this->error("Tenant with ID {$tenantId} not found.");
                    return Command::FAILURE;
                }

                if (!$tenant->plan) {
                    $this->warn("Tenant {$tenant->name} has no plan assigned.");
                    return Command::SUCCESS;
                }

                $this->info("Checking usage for tenant: {$tenant->name}");
                $notificationService->checkAndNotifyTenant($tenant);
                $this->info("✓ Usage check completed for tenant: {$tenant->name}");
            } else {
                $this->info('Checking usage for all tenants...');
                $notificationService->checkAndNotifyAllTenants();
                $this->info('✓ Usage check completed for all tenants');
            }

            if (!$this->option('quiet')) {
                $this->info('Usage check completed successfully.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to check plan usage: ' . $e->getMessage());
            Log::error('Plan usage check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}

