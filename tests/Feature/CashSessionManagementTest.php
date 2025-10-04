<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class CashSessionManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'cash_sessions.open',
            'cash_sessions.close',
            'cash_sessions.view',
            'cash_sessions.manage'
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::create(['name' => $permission]);
        }

        // Create store and user
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        
        // Create and assign cashier role
        $cashierRole = Role::create(['name' => 'cashier']);
        $this->user->assignRole($cashierRole);
        
        // Give necessary permissions
        $this->user->givePermissionTo($permissions);

        Sanctum::actingAs($this->user);
    }

    public function test_can_open_cash_session()
    {
        $openingBalance = 100.00;

        $response = $this->postJson('/api/v1/cash-sessions', [
            'opening_balance' => $openingBalance,
            'notes' => 'Starting shift'
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Cash session opened successfully'
            ]);

        $this->assertDatabaseHas('cash_sessions', [
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'opening_balance' => $openingBalance,
            'status' => 'open',
            'notes' => 'Starting shift'
        ]);
    }

    public function test_cannot_open_multiple_cash_sessions()
    {
        // Create an existing open session
        CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open'
        ]);

        $response = $this->postJson('/api/v1/cash-sessions', [
            'opening_balance' => 100.00
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You already have an open cash session. Please close it first.'
            ]);
    }

    public function test_can_close_cash_session()
    {
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'opening_balance' => 100.00,
            'status' => 'open'
        ]);

        $closingBalance = 150.00;

        $response = $this->postJson("/api/v1/cash-sessions/{$session->id}/close", [
            'closing_balance' => $closingBalance,
            'notes' => 'End of shift'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cash session closed successfully'
            ]);

        $session->refresh();
        $this->assertEquals('closed', $session->status);
        $this->assertEquals($closingBalance, $session->closing_balance);
        $this->assertNotNull($session->closed_at);
    }

    public function test_cannot_close_already_closed_session()
    {
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed'
        ]);

        $response = $this->postJson("/api/v1/cash-sessions/{$session->id}/close", [
            'closing_balance' => 150.00
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cash session is already closed'
            ]);
    }

    public function test_can_only_close_own_session()
    {
        $otherUser = User::factory()->create(['store_id' => $this->store->id]);
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $otherUser->id,
            'status' => 'open'
        ]);

        $response = $this->postJson("/api/v1/cash-sessions/{$session->id}/close", [
            'closing_balance' => 150.00
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You can only close your own cash session'
            ]);
    }

    public function test_can_get_current_open_session()
    {
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open'
        ]);

        $response = $this->getJson('/api/v1/cash-sessions-current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $session->id,
                    'status' => 'open'
                ]
            ]);
    }

    public function test_returns_404_when_no_current_session()
    {
        $response = $this->getJson('/api/v1/cash-sessions-current');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No open cash session found'
            ]);
    }

    public function test_can_list_cash_sessions()
    {
        CashSession::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/v1/cash-sessions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'opening_balance',
                            'status',
                            'opened_at',
                            'user'
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_filter_sessions_by_status()
    {
        CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open'
        ]);

        CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed'
        ]);

        $response = $this->getJson('/api/v1/cash-sessions?status=open');

        $response->assertStatus(200);
        $sessions = $response->json('data.data');
        $this->assertCount(1, $sessions);
        $this->assertEquals('open', $sessions[0]['status']);
    }

    public function test_can_get_cash_session_summary()
    {
        CashSession::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open'
        ]);

        CashSession::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
            'cash_sales' => 100.00,
            'cash_expenses' => 20.00,
            'variance' => 5.00
        ]);

        $response = $this->getJson('/api/v1/cash-sessions-summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_sessions' => 5,
                    'open_sessions' => 2,
                    'closed_sessions' => 3,
                    'total_cash_sales' => 300.00,
                    'total_cash_expenses' => 60.00,
                    'total_variance' => 15.00
                ]
            ]);
    }

    public function test_calculates_expected_balance_correctly()
    {
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'opening_balance' => 100.00,
            'status' => 'open'
        ]);

        // Create cash payments
        Payment::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'payment_method' => 'cash',
            'amount' => 50.00,
            'status' => 'completed',
            'created_at' => $session->opened_at->addHour()
        ]);

        // Create expenses
        Expense::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'cash_session_id' => $session->id,
            'amount' => 10.00
        ]);

        $session->calculateExpectedBalance();
        $session->refresh();

        // Expected: 100 (opening) + 100 (cash sales) - 20 (expenses) = 180
        $this->assertEquals(180.00, $session->expected_balance);
        $this->assertEquals(100.00, $session->cash_sales);
        $this->assertEquals(20.00, $session->cash_expenses);
    }

    public function test_detects_variance_correctly()
    {
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'opening_balance' => 100.00,
            'expected_balance' => 180.00,
            'closing_balance' => 175.00,
            'variance' => -5.00,
            'status' => 'closed'
        ]);

        $this->assertTrue($session->hasVariance());

        $session->update(['variance' => 0.00]);
        $this->assertFalse($session->hasVariance());
    }

    public function test_validates_opening_balance()
    {
        $response = $this->postJson('/api/v1/cash-sessions', [
            'opening_balance' => -10.00
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_balance']);
    }

    public function test_validates_closing_balance()
    {
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open'
        ]);

        $response = $this->postJson("/api/v1/cash-sessions/{$session->id}/close", [
            'closing_balance' => 'invalid'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['closing_balance']);
    }

    public function test_cannot_update_closed_session()
    {
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed'
        ]);

        $response = $this->putJson("/api/v1/cash-sessions/{$session->id}", [
            'notes' => 'Updated notes'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot update a closed cash session'
            ]);
    }

    public function test_cannot_delete_open_session()
    {
        $this->user->givePermissionTo('cash_sessions.manage');
        
        $session = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open'
        ]);

        $response = $this->deleteJson("/api/v1/cash-sessions/{$session->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete an open cash session. Please close it first.'
            ]);
    }
}