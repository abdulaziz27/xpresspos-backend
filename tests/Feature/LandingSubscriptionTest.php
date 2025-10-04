<?php

namespace Tests\Feature;

use App\Jobs\ProcessLandingSubscription;
use App\Models\LandingSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LandingSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_persists_a_subscription_and_dispatches_processing_job(): void
    {
        Queue::fake();

        $response = $this->post(route('landing.subscribe'), [
            'email' => 'lead@example.com',
            'name' => 'Prospect',
            'company' => 'Prospect LLC',
            'phone' => '+62 812-3456-7890',
            'country' => 'Indonesia',
            'preferred_contact_method' => 'whatsapp',
            'notes' => 'Call me in the afternoon.',
            'plan' => 'growth',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('landing_subscriptions', [
            'email' => 'lead@example.com',
            'name' => 'Prospect',
            'company' => 'Prospect LLC',
            'plan' => 'growth',
            'status' => 'pending',
            'stage' => 'new',
            'preferred_contact_method' => 'whatsapp',
            'country' => 'Indonesia',
        ]);

        $subscription = LandingSubscription::first();

        Queue::assertPushed(ProcessLandingSubscription::class, function (ProcessLandingSubscription $job) use ($subscription) {
            return $job->subscriptionId === $subscription->id;
        });
    }
}
