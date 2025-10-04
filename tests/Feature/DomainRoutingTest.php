<?php

namespace Tests\Feature;

use Tests\TestCase;

class DomainRoutingTest extends TestCase
{
    /** @test */
    public function landing_domain_returns_home_view(): void
    {
        $this->get('http://'.config('domains.landing').'/')
            ->assertOk()
            ->assertViewIs('landing.pages.home');
    }

    /** @test */
    public function owner_domain_returns_dashboard_view(): void
    {
        $this->get('http://'.config('domains.owner').'/')
            ->assertOk()
            ->assertViewIs('owner.dashboard');
    }

    /** @test */
    public function admin_domain_redirects_to_filament_login(): void
    {
        $response = $this->get('http://'.config('domains.admin').'/');

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    /** @test */
    public function api_domain_ping_endpoint_returns_ok(): void
    {
        $this->getJson('http://'.config('domains.api').'/v1/ping')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
