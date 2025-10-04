<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\CashSession;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete'
        ];

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

    public function test_can_create_expense()
    {
        $expenseData = [
            'category' => 'office_supplies',
            'description' => 'Office paper and pens',
            'amount' => 25.50,
            'receipt_number' => 'RCP-001',
            'vendor' => 'Office Depot',
            'expense_date' => now()->toDateString(),
            'notes' => 'Monthly office supplies'
        ];

        $response = $this->postJson('/api/v1/expenses', $expenseData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Expense created successfully'
            ]);

        $this->assertDatabaseHas('expenses', [
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'category' => 'office_supplies',
            'description' => 'Office paper and pens',
            'amount' => 25.50,
            'receipt_number' => 'RCP-001',
            'vendor' => 'Office Depot'
        ]);
    }

    public function test_can_create_expense_linked_to_cash_session()
    {
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'opening_balance' => 100.00
        ]);

        $expenseData = [
            'cash_session_id' => $cashSession->id,
            'category' => 'utilities',
            'description' => 'Electricity bill',
            'amount' => 50.00
        ];

        $response = $this->postJson('/api/v1/expenses', $expenseData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('expenses', [
            'cash_session_id' => $cashSession->id,
            'category' => 'utilities',
            'amount' => 50.00
        ]);

        // Check that cash session expected balance was updated
        $cashSession->refresh();
        $this->assertEquals(50.00, $cashSession->cash_expenses);
    }

    public function test_cannot_create_expense_for_closed_cash_session()
    {
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed'
        ]);

        $expenseData = [
            'cash_session_id' => $cashSession->id,
            'category' => 'utilities',
            'description' => 'Electricity bill',
            'amount' => 50.00
        ];

        $response = $this->postJson('/api/v1/expenses', $expenseData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot add expense to a closed or non-existent cash session'
            ]);
    }

    public function test_can_list_expenses()
    {
        Expense::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/v1/expenses');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'category',
                            'description',
                            'amount',
                            'expense_date',
                            'user'
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_filter_expenses_by_category()
    {
        Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'category' => 'office_supplies'
        ]);

        Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'category' => 'utilities'
        ]);

        $response = $this->getJson('/api/v1/expenses?category=office_supplies');

        $response->assertStatus(200);
        $expenses = $response->json('data.data');
        $this->assertCount(1, $expenses);
        $this->assertEquals('office_supplies', $expenses[0]['category']);
    }

    public function test_can_filter_expenses_by_date_range()
    {
        $startDate = now()->subDays(5)->toDateString();
        $endDate = now()->toDateString();

        Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'expense_date' => now()->subDays(3)->toDateString()
        ]);

        Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'expense_date' => now()->subDays(10)->toDateString()
        ]);

        $response = $this->getJson("/api/v1/expenses?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $expenses = $response->json('data.data');
        $this->assertCount(1, $expenses);
    }

    public function test_can_update_expense()
    {
        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'category' => 'office_supplies',
            'amount' => 25.00
        ]);

        $updateData = [
            'category' => 'utilities',
            'amount' => 30.00,
            'description' => 'Updated description'
        ];

        $response = $this->putJson("/api/v1/expenses/{$expense->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Expense updated successfully'
            ]);

        $expense->refresh();
        $this->assertEquals('utilities', $expense->category);
        $this->assertEquals(30.00, $expense->amount);
        $this->assertEquals('Updated description', $expense->description);
    }

    public function test_cannot_update_expense_linked_to_closed_session()
    {
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'closed'
        ]);

        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'cash_session_id' => $cashSession->id
        ]);

        $response = $this->putJson("/api/v1/expenses/{$expense->id}", [
            'amount' => 50.00
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot update expense linked to a closed cash session'
            ]);
    }

    public function test_can_delete_expense()
    {
        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->deleteJson("/api/v1/expenses/{$expense->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Expense deleted successfully'
            ]);

        $this->assertDatabaseMissing('expenses', [
            'id' => $expense->id
        ]);
    }

    public function test_cannot_delete_expense_linked_to_closed_session()
    {
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'closed'
        ]);

        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'cash_session_id' => $cashSession->id
        ]);

        $response = $this->deleteJson("/api/v1/expenses/{$expense->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete expense linked to a closed cash session'
            ]);
    }

    public function test_can_get_expense_categories()
    {
        $response = $this->getJson('/api/v1/expense-categories');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'office_supplies',
                    'utilities',
                    'maintenance',
                    'marketing',
                    'travel',
                    'meals',
                    'professional_services',
                    'inventory',
                    'equipment',
                    'rent',
                    'insurance',
                    'taxes',
                    'miscellaneous'
                ]
            ]);
    }

    public function test_can_get_expense_summary()
    {
        Expense::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'category' => 'office_supplies',
            'amount' => 25.00
        ]);

        Expense::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'category' => 'utilities',
            'amount' => 50.00
        ]);

        $response = $this->getJson('/api/v1/expenses-summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_expenses' => 5,
                    'total_amount' => 175.00,
                    'average_amount' => 35.00
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'expenses_by_category',
                    'expenses_by_user',
                    'recent_expenses'
                ]
            ]);
    }

    public function test_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/expenses', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'description', 'amount']);
    }

    public function test_validates_category()
    {
        $response = $this->postJson('/api/v1/expenses', [
            'category' => 'invalid_category',
            'description' => 'Test expense',
            'amount' => 25.00
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_validates_amount()
    {
        $response = $this->postJson('/api/v1/expenses', [
            'category' => 'office_supplies',
            'description' => 'Test expense',
            'amount' => -10.00
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $response = $this->postJson('/api/v1/expenses', [
            'category' => 'office_supplies',
            'description' => 'Test expense',
            'amount' => 0
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_validates_expense_date()
    {
        $response = $this->postJson('/api/v1/expenses', [
            'category' => 'office_supplies',
            'description' => 'Test expense',
            'amount' => 25.00,
            'expense_date' => now()->addDay()->toDateString()
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expense_date']);
    }

    public function test_validates_cash_session_exists()
    {
        $response = $this->postJson('/api/v1/expenses', [
            'cash_session_id' => 'non-existent-id',
            'category' => 'office_supplies',
            'description' => 'Test expense',
            'amount' => 25.00
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cash_session_id']);
    }

    public function test_updates_cash_session_when_expense_modified()
    {
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'opening_balance' => 100.00
        ]);

        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'cash_session_id' => $cashSession->id,
            'amount' => 25.00
        ]);

        // Update expense amount
        $this->putJson("/api/v1/expenses/{$expense->id}", [
            'amount' => 50.00
        ]);

        // Check that cash session was updated
        $cashSession->refresh();
        $this->assertEquals(50.00, $cashSession->cash_expenses);
    }

    public function test_updates_cash_session_when_expense_deleted()
    {
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'opening_balance' => 100.00,
            'cash_expenses' => 25.00
        ]);

        $expense = Expense::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'cash_session_id' => $cashSession->id,
            'amount' => 25.00
        ]);

        $this->deleteJson("/api/v1/expenses/{$expense->id}");

        // Check that cash session was updated
        $cashSession->refresh();
        $this->assertEquals(0.00, $cashSession->cash_expenses);
    }
}