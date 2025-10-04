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

class CashFlowReportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = ['reports.view'];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::create(['name' => $permission]);
        }

        // Create store and user
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        
        // Create and assign manager role
        $managerRole = Role::create(['name' => 'manager']);
        $this->user->assignRole($managerRole);
        
        // Give necessary permissions
        $this->user->givePermissionTo($permissions);

        Sanctum::actingAs($this->user);
    }

    public function test_can_get_daily_cash_flow_report()
    {
        $date = now()->toDateString();

        // Create cash session
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
            'opening_balance' => 100.00,
            'closing_balance' => 180.00,
            'cash_sales' => 100.00,
            'cash_expenses' => 20.00,
            'variance' => 0.00,
            'opened_at' => now()
        ]);

        // Create payments
        Payment::factory()->create([
            'store_id' => $this->store->id,
            'payment_method' => 'cash',
            'amount' => 50.00,
            'status' => 'completed',
            'created_at' => now()
        ]);

        Payment::factory()->create([
            'store_id' => $this->store->id,
            'payment_method' => 'credit_card',
            'amount' => 75.00,
            'status' => 'completed',
            'created_at' => now()
        ]);

        // Create expenses
        Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'category' => 'office_supplies',
            'amount' => 25.00,
            'expense_date' => $date
        ]);

        $response = $this->getJson("/api/v1/reports/cash-flow/daily?date={$date}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daily cash flow report generated successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'cash_sessions' => [
                        'total_sessions',
                        'open_sessions',
                        'closed_sessions',
                        'total_opening_balance',
                        'total_closing_balance',
                        'total_cash_sales',
                        'total_cash_expenses',
                        'total_variance',
                        'sessions_with_variance',
                        'sessions'
                    ],
                    'payments_by_method',
                    'expenses_by_category',
                    'summary' => [
                        'total_revenue',
                        'total_expenses',
                        'net_cash_flow',
                        'cash_revenue',
                        'non_cash_revenue'
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['cash_sessions']['total_sessions']);
        $this->assertEquals(125.00, $data['summary']['total_revenue']);
        $this->assertEquals(25.00, $data['summary']['total_expenses']);
        $this->assertEquals(100.00, $data['summary']['net_cash_flow']);
    }

    public function test_can_get_payment_method_breakdown()
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // Create payments with different methods
        Payment::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'payment_method' => 'cash',
            'amount' => 50.00,
            'status' => 'completed',
            'created_at' => now()->subDays(2)
        ]);

        Payment::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'payment_method' => 'credit_card',
            'amount' => 75.00,
            'status' => 'completed',
            'created_at' => now()->subDays(2)
        ]);

        $response = $this->getJson("/api/v1/reports/cash-flow/payment-methods?start_date={$startDate}&end_date={$endDate}&group_by=day");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment method breakdown report generated successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start_date', 'end_date', 'group_by'],
                    'breakdown',
                    'summary' => [
                        'total_transactions',
                        'total_amount',
                        'methods_summary'
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(5, $data['summary']['total_transactions']);
        $this->assertEquals(300.00, $data['summary']['total_amount']);
    }

    public function test_can_get_cash_variance_analysis()
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // Create sessions with variance
        CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
            'variance' => 5.00,
            'opened_at' => now()->subDays(2)
        ]);

        CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
            'variance' => -3.00,
            'opened_at' => now()->subDays(1)
        ]);

        CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
            'variance' => 0.00,
            'opened_at' => now()
        ]);

        $response = $this->getJson("/api/v1/reports/cash-flow/variance-analysis?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cash variance analysis report generated successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'summary' => [
                        'total_sessions',
                        'sessions_with_variance',
                        'total_variance',
                        'average_variance',
                        'positive_variance',
                        'negative_variance'
                    ],
                    'variance_by_user',
                    'sessions_with_significant_variance'
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['summary']['total_sessions']);
        $this->assertEquals(2, $data['summary']['sessions_with_variance']);
        $this->assertEquals(2.00, $data['summary']['total_variance']);
        $this->assertEquals(5.00, $data['summary']['positive_variance']);
        $this->assertEquals(-3.00, $data['summary']['negative_variance']);
    }

    public function test_can_get_shift_summary()
    {
        $date = now()->toDateString();

        // Create cash session
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
            'opening_balance' => 100.00,
            'closing_balance' => 175.00,
            'opened_at' => now()->subHours(8),
            'closed_at' => now()
        ]);

        // Create expenses for the session
        Expense::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'cash_session_id' => $cashSession->id,
            'amount' => 15.00
        ]);

        // Create payments during session
        Payment::factory()->create([
            'store_id' => $this->store->id,
            'payment_method' => 'cash',
            'amount' => 60.00,
            'status' => 'completed',
            'created_at' => now()->subHours(4)
        ]);

        $response = $this->getJson("/api/v1/reports/cash-flow/shift-summary?start_date={$date}&end_date={$date}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shift-based financial summary generated successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'shifts' => [
                        '*' => [
                            'session' => [
                                'id',
                                'user',
                                'status',
                                'opened_at',
                                'closed_at',
                                'duration_hours'
                            ],
                            'cash_flow' => [
                                'opening_balance',
                                'closing_balance',
                                'expected_balance',
                                'variance',
                                'cash_sales',
                                'cash_expenses'
                            ],
                            'payments_by_method',
                            'expenses' => [
                                'total_expenses',
                                'expense_count',
                                'expenses_by_category'
                            ],
                            'performance' => [
                                'total_revenue',
                                'net_cash_flow',
                                'transactions_count',
                                'average_transaction'
                            ]
                        ]
                    ],
                    'summary' => [
                        'total_shifts',
                        'open_shifts',
                        'closed_shifts',
                        'total_revenue',
                        'total_expenses',
                        'net_cash_flow',
                        'total_variance'
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['summary']['total_shifts']);
        $this->assertEquals(0, $data['summary']['open_shifts']);
        $this->assertEquals(1, $data['summary']['closed_shifts']);
        
        $shift = $data['shifts'][0];
        $this->assertEquals(8, $shift['session']['duration_hours']);
        $this->assertEquals(30.00, $shift['expenses']['total_expenses']);
        $this->assertEquals(2, $shift['expenses']['expense_count']);
    }

    public function test_validates_date_parameters()
    {
        $response = $this->getJson('/api/v1/reports/cash-flow/daily?date=invalid-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_validates_date_range_parameters()
    {
        $response = $this->getJson('/api/v1/reports/cash-flow/payment-methods?start_date=2024-01-01&end_date=2023-12-31');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_validates_group_by_parameter()
    {
        $response = $this->getJson('/api/v1/reports/cash-flow/payment-methods?group_by=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['group_by']);
    }

    public function test_filters_by_user_id()
    {
        $otherUser = User::factory()->create(['store_id' => $this->store->id]);
        
        // Create sessions for different users
        CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
            'variance' => 5.00
        ]);

        CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $otherUser->id,
            'status' => 'closed',
            'variance' => 10.00
        ]);

        // Debug the user ID format
        $userId = $this->user->id;
        
        $response = $this->getJson("/api/v1/reports/cash-flow/variance-analysis?user_id=" . $userId);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['summary']['total_sessions']);
        $this->assertEquals(5.00, $data['summary']['total_variance']);
    }

    public function test_requires_reports_permission()
    {
        // Remove permission
        $this->user->revokePermissionTo('reports.view');

        $response = $this->getJson('/api/v1/reports/cash-flow/daily');

        $response->assertStatus(403);
    }

    public function test_respects_tenant_scoping()
    {
        $otherStore = Store::factory()->create();
        
        // Create session in other store
        CashSession::factory()->create([
            'store_id' => $otherStore->id,
            'status' => 'closed',
            'variance' => 100.00
        ]);

        $response = $this->getJson('/api/v1/reports/cash-flow/variance-analysis');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(0, $data['summary']['total_sessions']);
    }
}