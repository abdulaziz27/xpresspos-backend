<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPlanApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_active_plans_in_display_order(): void
    {
        Plan::factory()->create([
            'name' => 'Legacy',
            'slug' => 'legacy',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $first = Plan::factory()->create([
            'name' => 'Basic',
            'slug' => 'basic',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $second = Plan::factory()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this->getJson('/api/v1/public/plans');

        $response->assertOk()
            ->assertJson([ 'success' => true ])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', $first->slug)
            ->assertJsonPath('data.1.slug', $second->slug)
            ->assertJsonPath('meta.count', 2);
    }
}
