<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_returns_json_error(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required to access this resource',
                ]
            ])
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'details'
                ],
                'meta' => [
                    'timestamp',
                    'version',
                    'request_id'
                ]
            ]);
    }

    public function test_authenticated_api_request_returns_json_response(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email'
                    ]
                ],
                'message',
                'meta'
            ]);
    }

    public function test_api_request_with_invalid_token_returns_json_error(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ]
            ]);
    }

    public function test_api_request_without_accept_header_still_returns_json(): void
    {
        // Test that API routes return JSON even without Accept header
        $response = $this->get('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ]
            ]);
    }

    public function test_validation_errors_return_json_format(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
            'password' => '123' // too short
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email',
                    'password'
                ]
            ]);
    }

    public function test_successful_login_returns_proper_json_structure(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'Test Device'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'store_id',
                        'store',
                        'roles',
                        'permissions'
                    ],
                    'token',
                    'token_type',
                    'expires_at'
                ],
                'message',
                'meta' => [
                    'timestamp',
                    'version',
                    'request_id'
                ]
            ]);
    }

    public function test_health_check_endpoint_works(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'version' => 'v1'
            ])
            ->assertJsonStructure([
                'status',
                'services',
                'timestamp',
                'version'
            ]);
    }
}
