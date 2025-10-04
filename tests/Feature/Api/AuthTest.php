<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'admin_sistem']);
        Role::create(['name' => 'owner']);
        Role::create(['name' => 'manager']);
        Role::create(['name' => 'cashier']);
    }

    public function test_health_endpoint_returns_success()
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'services' => [
                        'database',
                        'cache',
                    ],
                    'timestamp',
                    'version'
                ])
                ->assertJson([
                    'status' => 'healthy',
                    'version' => 'v1'
                ]);
    }

    public function test_login_with_valid_credentials()
    {
        // Create a store first
        $store = \App\Models\Store::factory()->create();
        
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'store_id' => $store->id,
        ]);
        $user->assignRole('owner');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'store_id',
                            'roles',
                            'permissions',
                        ],
                        'token',
                        'token_type',
                        'expires_at',
                    ],
                    'message',
                    'meta' => [
                        'timestamp',
                        'version',
                        'request_id',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'token_type' => 'Bearer',
                        'user' => [
                            'email' => 'test@example.com',
                            'store_id' => $store->id,
                        ]
                    ],
                    'message' => 'Login successful',
                    'meta' => [
                        'version' => 'v1'
                    ]
                ]);
    }

    public function test_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_with_valid_token()
    {
        $user = User::factory()->create();
        $user->assignRole('owner');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logout successful',
                    'meta' => [
                        'version' => 'v1'
                    ]
                ]);
    }

    public function test_get_user_with_valid_token()
    {
        $user = User::factory()->create([
            'store_id' => null, // No store for this test
        ]);
        $user->assignRole('owner');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
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
                            'permissions',
                            'created_at',
                            'updated_at',
                        ]
                    ],
                    'message',
                    'meta'
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user' => [
                            'email' => $user->email,
                            'store_id' => null,
                        ]
                    ],
                    'message' => 'User data retrieved successfully',
                ]);
    }

    public function test_change_password_with_valid_current_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);
        $user->assignRole('owner');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Password changed successfully',
                ]);
    }

    public function test_change_password_with_invalid_current_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);
        $user->assignRole('owner');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['current_password']);
    }

    public function test_get_user_sessions()
    {
        $user = User::factory()->create();
        $user->assignRole('owner');
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/sessions');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'sessions' => [
                            '*' => [
                                'id',
                                'name',
                                'is_current',
                                'last_used_at',
                                'created_at',
                                'expires_at',
                            ]
                        ],
                        'total'
                    ],
                    'message',
                    'meta'
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total' => 1,
                    ],
                    'message' => 'Active sessions retrieved successfully',
                ]);
    }
}