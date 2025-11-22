<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Plan;

class CheckUserPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:check-plan {email : The email address of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the current subscription plan for a user by email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }
        
        $this->info("User: {$user->name} ({$user->email})");
        $this->newLine();
        
        // Get tenant
        $tenant = $user->currentTenant();
        
        if (!$tenant) {
            $this->warn("User has no tenant associated.");
            $this->newLine();
            $this->error("PLAN TYPE: NO SUBSCRIPTION");
            return 0;
        }
        
        $this->info("Tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->newLine();
        
        // Check landing subscriptions first
        $landingSubscriptions = \App\Models\LandingSubscription::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        if ($landingSubscriptions->count() > 0) {
            $this->info("=== LANDING SUBSCRIPTIONS ({$landingSubscriptions->count()}) ===");
            foreach ($landingSubscriptions as $ls) {
                $planName = $ls->plan ? $ls->plan->name : ($ls->plan_id ?? 'Unknown');
                $this->line("  ID: {$ls->id}");
                $this->line("  Plan: {$planName} (ID: {$ls->plan_id})");
                $this->line("  Status: {$ls->status}");
                $this->line("  Payment Status: {$ls->payment_status}");
                $this->line("  Provisioned: " . ($ls->provisioned_user_id ? 'Yes (User ID: ' . $ls->provisioned_user_id . ')' : 'No'));
                $this->line("  Subscription ID: " . ($ls->subscription_id ?? 'None'));
                $this->line("  Xendit Invoice: " . ($ls->xendit_invoice_id ?? 'None'));
                
                // Check SubscriptionPayment records
                $payments = $ls->subscriptionPayments;
                if ($payments->count() > 0) {
                    $this->line("  Payment Records: {$payments->count()}");
                    foreach ($payments as $payment) {
                        $this->line("    - Payment ID: {$payment->id}, Status: {$payment->status}, Amount: Rp " . number_format($payment->amount, 0, ',', '.'));
                    }
                } else {
                    $this->line("  Payment Records: None (Payment may not be created yet)");
                }
                
                $this->line("  Created: {$ls->created_at}");
                $this->newLine();
            }
        }
        
        // Get active subscription
        $subscription = $tenant->activeSubscription();
        
        if (!$subscription) {
            $this->warn("No active subscription found for this tenant.");
            
            // Check for any subscriptions
            $allSubscriptions = $tenant->subscriptions()->with('plan')->orderBy('created_at', 'desc')->get();
            
            if ($allSubscriptions->count() > 0) {
                $this->info("Found {$allSubscriptions->count()} subscription(s) (may be expired):");
                $this->newLine();
                
                foreach ($allSubscriptions as $sub) {
                    $status = $sub->isActive() ? '✓ Active' : ($sub->hasExpired() ? '✗ Expired' : '⚠ Inactive');
                    $planSlug = $sub->plan->slug;
                    $this->line("  - Plan: {$sub->plan->name} ({$planSlug})");
                    $this->line("    Status: {$status}");
                    $this->line("    Billing: {$sub->billing_cycle}");
                    $this->line("    Ends at: {$sub->ends_at->format('Y-m-d H:i:s')}");
                    if ($sub->trial_ends_at) {
                        $this->line("    Trial ends at: {$sub->trial_ends_at->format('Y-m-d H:i:s')}");
                    }
                    $this->newLine();
                }
                
                // Show plan type from last subscription
                $lastPlan = $allSubscriptions->first()->plan;
                $this->newLine();
                $this->info("=== PLAN TYPE (Last Subscription) ===");
                $this->displayPlanType($lastPlan->slug);
            } else {
                $this->newLine();
                $this->error("PLAN TYPE: NO SUBSCRIPTION");
                
                // Check if payment was made but provisioning failed
                $paidLandingSubs = $landingSubscriptions->where('payment_status', 'paid')->whereNull('subscription_id');
                if ($paidLandingSubs->count() > 0) {
                    $this->warn("⚠ WARNING: Found {$paidLandingSubs->count()} paid landing subscription(s) but no active subscription!");
                    $this->warn("   This means payment was successful but provisioning may have failed.");
                    $this->warn("   Run: php artisan subscription:provision --email={$email} --mark-paid");
                }
            }
            
            return 0;
        }
        
        $plan = $subscription->plan;
        
        // Show plan type prominently at the top
        $this->newLine();
        $this->info("═══════════════════════════════════════");
        $this->displayPlanType($plan->slug);
        $this->info("═══════════════════════════════════════");
        $this->newLine();
        
        // Display subscription info
        $this->info("=== ACTIVE SUBSCRIPTION ===");
        $this->line("Plan: {$plan->name} ({$plan->slug})");
        $this->line("Plan ID: {$plan->id}");
        $this->line("Billing Cycle: {$subscription->billing_cycle}");
        $this->line("Status: {$subscription->status}");
        $this->line("Starts at: {$subscription->starts_at->format('Y-m-d H:i:s')}");
        $this->line("Ends at: {$subscription->ends_at->format('Y-m-d H:i:s')}");
        
        if ($subscription->trial_ends_at) {
            $trialStatus = $subscription->onTrial() ? 'Active' : 'Ended';
            $this->line("Trial ends at: {$subscription->trial_ends_at->format('Y-m-d H:i:s')} ({$trialStatus})");
        }
        
        $daysRemaining = $subscription->daysUntilExpiration();
        if ($daysRemaining > 0) {
            $this->line("Days remaining: {$daysRemaining}");
        } else {
            $this->warn("Subscription has expired!");
        }
        
        $this->newLine();
        
        // Display plan details
        $this->info("=== PLAN DETAILS ===");
        $this->line("Name: {$plan->name}");
        $this->line("Slug: {$plan->slug}");
        $this->line("Price: Rp " . number_format($plan->price, 0, ',', '.') . "/month");
        $this->line("Annual Price: Rp " . number_format($plan->annual_price, 0, ',', '.') . "/year");
        
        if (!empty($plan->features)) {
            $this->newLine();
            $this->line("Features:");
            foreach ($plan->features as $feature) {
                $this->line("  - " . ucwords(str_replace('_', ' ', $feature)));
            }
        }
        
        if (!empty($plan->limits)) {
            $this->newLine();
            $this->line("Limits:");
            foreach ($plan->limits as $key => $limit) {
                $limitValue = $limit == -1 || $limit == 0 ? 'Unlimited' : number_format($limit, 0, ',', '.');
                $this->line("  - " . ucwords(str_replace('_', ' ', $key)) . ": {$limitValue}");
            }
        }
        
        return 0;
    }
    
    /**
     * Display plan type prominently
     */
    private function displayPlanType(string $slug): void
    {
        if ($slug === 'pro') {
            $this->line("  ✓ PLAN TYPE: <fg=cyan;options=bold>PRO</>");
        } elseif ($slug === 'enterprise') {
            $this->line("  ✓ PLAN TYPE: <fg=green;options=bold>ENTERPRISE</>");
        } elseif ($slug === 'basic') {
            $this->line("  PLAN TYPE: <fg=yellow;options=bold>BASIC</>");
        } else {
            $this->line("  PLAN TYPE: <fg=white;options=bold>" . strtoupper($slug) . "</>");
        }
    }
}
