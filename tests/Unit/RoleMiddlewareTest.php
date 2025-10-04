<?php

namespace Tests\Unit;

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role and permission seeder
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_middleware_allows_user_with_required_role()
    {
        $user = User::factory()->create();
        $user->assignRole('owner');
        
        Sanctum::actingAs($user);
        
        $middleware = new RoleMiddleware();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'owner');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function test_middleware_allows_user_with_any_of_multiple_roles()
    {
        $user = User::factory()->create();
        $user->assignRole('manager');
        
        Sanctum::actingAs($user);
        
        $middleware = new RoleMiddleware();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'owner', 'manager', 'cashier');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function test_middleware_blocks_user_without_required_role()
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');
        
        Sanctum::actingAs($user);
        
        $middleware = new RoleMiddleware();
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'owner');
        
        $this->assertEquals(403, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('UNAUTHORIZED', $responseData['error']['code']);
        $this->assertEquals(['owner'], $responseData['error']['required_roles']);
        $this->assertEquals(['cashier'], $responseData['error']['user_roles']);
    }

    public function test_middleware_blocks_unauthenticated_user()
    {
        $middleware = new RoleMiddleware();
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'owner');
        
        $this->assertEquals(401, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('UNAUTHENTICATED', $responseData['error']['code']);
    }
}