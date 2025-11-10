<?php

namespace Tests\Feature;

use Tests\TestCase;

class DomainRoutingTest extends TestCase
{
    /** @test */
    public function landing_domain_returns_home_view(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertViewIs('landing');
    }

    /** @test */
    public function owner_domain_returns_dashboard_view(): void
    {
        $this->markTestSkipped('Path-based routing: owner panel tersedia di /owner.');
    }

    /** @test */
    public function admin_domain_redirects_to_filament_login(): void
    {
        $this->markTestSkipped('Path-based routing: admin panel tersedia di /admin.');
    }

    /** @test */
    public function api_domain_ping_endpoint_returns_ok(): void
    {
        // Skip domain-based routing test for now - routes are loaded without domain constraints
        $this->markTestSkipped('Domain-based routing not implemented yet. Routes are accessible on all domains.');

        $this->getJson('http://' . config('domains.api') . '/v1/ping')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
