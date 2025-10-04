<?php

namespace Tests\Unit;

use App\Models\LandingSubscription;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use App\Models\User;
use App\Services\TrialProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrialProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    /** @test */
    public function it_provisions_store_subscription_and_owner_from_lead(): void
    {
        $plan = Plan::factory()->create([
            'name' => 'Basic',
            'slug' => 'basic',
            'price' => 150000,
            'annual_price' => 1500000,
            'features' => ['pos', 'basic_reports'],
            'limits' => [
                'products' => 20,
                'users' => 2,
                'transactions' => 12000,
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $lead = LandingSubscription::factory()->create([
            'email' => 'lead@example.com',
            'name' => 'Lead Owner',
            'company' => 'Lead Coffee',
            'plan' => 'basic',
            'status' => 'pending',
            'stage' => 'new',
        ]);

        $admin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin_sistem');

        $service = app(TrialProvisioningService::class);

        $result = $service->provisionFromLead($lead, $admin);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('temporary_password', $result);
        $this->assertNotEmpty($result['temporary_password']);

        $lead->refresh();

        $this->assertSame('converted', $lead->status);
        $this->assertSame('converted', $lead->stage);
        $this->assertNotNull($lead->provisioned_store_id);
        $this->assertNotNull($lead->provisioned_user_id);
        $this->assertNotNull($lead->provisioned_at);

        $store = Store::findOrFail($lead->provisioned_store_id);
        $owner = User::findOrFail($lead->provisioned_user_id);

        $this->assertSame($store->id, $owner->store_id);
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertEquals('Lead Coffee', $store->name);

        $subscription = Subscription::where('store_id', $store->id)->first();
        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->starts_at->isToday());
        $this->assertEquals($plan->id, $subscription->plan_id);

        $usage = SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('feature_type', 'transactions')
            ->first();

        $this->assertNotNull($usage);
        $this->assertSame(0, $usage->current_usage);
        $this->assertSame(12000, $usage->annual_quota);
    }

    /** @test */
    public function it_returns_error_when_no_active_plan_available(): void
    {
        $lead = LandingSubscription::factory()->create([
            'plan' => 'non-existent',
        ]);

        $service = app(TrialProvisioningService::class);

        $result = $service->provisionFromLead($lead);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No active plan', $result['message']);
    }
}
