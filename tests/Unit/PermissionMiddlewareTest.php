<?php

namespace Tests\Unit;

use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role and permission seeder
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_middleware_allows_system_admin_access()
    {
        $user = User::factory()->create();
        $user->assignRole('admin_sistem');
        
        Sanctum::actingAs($user);
        
        $middleware = new PermissionMiddleware();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'products.delete');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function test_middleware_allows_user_with_permission()
    {
        $user = User::factory()->create();
        $user->assignRole('owner');
        
        Sanctum::actingAs($user);
        
        $middleware = new PermissionMiddleware();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'products.view');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function test_middleware_blocks_user_without_permission()
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');
        
        Sanctum::actingAs($user);
        
        $middleware = new PermissionMiddleware();
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'products.delete');
        
        $this->assertEquals(403, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('UNAUTHORIZED', $responseData['error']['code']);
        $this->assertEquals('products.delete', $responseData['error']['required_permission']);
    }

    public function test_middleware_blocks_unauthenticated_user()
    {
        $middleware = new PermissionMiddleware();
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'products.view');
        
        $this->assertEquals(401, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('UNAUTHENTICATED', $responseData['error']['code']);
    }
}