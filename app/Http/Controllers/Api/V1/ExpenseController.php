<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\CashSession;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'tenant.scope']);
        // Remove strict permission middleware for MVP testing
        // Owner role should have access to all expense operations
    }

    /**
     * Display a listing of expenses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with(['user:id,name,email', 'cashSession:id,opened_at,closed_at,status'])
            ->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('expense_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by cash session
        if ($request->has('cash_session_id')) {
            $query->where('cash_session_id', $request->cash_session_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by vendor
        if ($request->has('vendor')) {
            $query->where('vendor', 'like', '%' . $request->vendor . '%');
        }

        // Filter by amount range
        if ($request->has('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->has('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        $expenses = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $expenses,
            'message' => 'Expenses retrieved successfully'
        ]);
    }

    /**
     * Store a newly created expense.
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Validate cash session if provided
            if ($request->cash_session_id) {
                $cashSession = CashSession::find($request->cash_session_id);
                if (!$cashSession || $cashSession->status === 'closed') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add expense to a closed or non-existent cash session'
                    ], 422);
                }
            }

            $expense = Expense::create([
                'store_id' => request()->user()->store_id,
                'user_id' => request()->user()->id,
                'cash_session_id' => $request->cash_session_id,
                'category' => $request->category,
                'description' => $request->description,
                'amount' => $request->amount,
                'receipt_number' => $request->receipt_number,
                'vendor' => $request->vendor,
                'expense_date' => $request->expense_date ?? now()->toDateString(),
                'notes' => $request->notes,
            ]);

            // Update cash session expected balance if expense is linked to a session
            if ($expense->cash_session_id) {
                $expense->cashSession->calculateExpectedBalance();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $expense->load(['user:id,name,email', 'cashSession:id,opened_at,closed_at,status']),
                'message' => 'Expense created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create expense',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified expense.
     */
    public function show(string $id): JsonResponse
    {
        $expense = Expense::with(['user:id,name,email', 'cashSession:id,opened_at,closed_at,status'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $expense,
            'message' => 'Expense retrieved successfully'
        ]);
    }

    /**
     * Update the specified expense.
     */
    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        try {
            DB::beginTransaction();

            // Check if expense is linked to a closed cash session
            if ($expense->cash_session_id && $expense->cashSession->status === 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update expense linked to a closed cash session'
                ], 422);
            }

            $oldCashSessionId = $expense->cash_session_id;

            $expense->update($request->validated());

            // Update cash session expected balance for old session
            if ($oldCashSessionId && $oldCashSessionId !== $expense->cash_session_id) {
                $oldCashSession = CashSession::find($oldCashSessionId);
                if ($oldCashSession && $oldCashSession->status === 'open') {
                    $oldCashSession->calculateExpectedBalance();
                }
            }

            // Update cash session expected balance for new session
            if ($expense->cash_session_id) {
                $expense->cashSession->calculateExpectedBalance();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $expense->load(['user:id,name,email', 'cashSession:id,opened_at,closed_at,status']),
                'message' => 'Expense updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update expense',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified expense.
     */
    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        try {
            DB::beginTransaction();

            // Check if expense is linked to a closed cash session
            if ($expense->cash_session_id && $expense->cashSession->status === 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete expense linked to a closed cash session'
                ], 422);
            }

            $cashSessionId = $expense->cash_session_id;

            $expense->delete();

            // Update cash session expected balance
            if ($cashSessionId) {
                $cashSession = CashSession::find($cashSessionId);
                if ($cashSession && $cashSession->status === 'open') {
                    $cashSession->calculateExpectedBalance();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete expense',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expense categories.
     */
    public function categories(): JsonResponse
    {
        $categories = [
            'office_supplies' => 'Office Supplies',
            'utilities' => 'Utilities',
            'maintenance' => 'Maintenance & Repairs',
            'marketing' => 'Marketing & Advertising',
            'travel' => 'Travel & Transportation',
            'meals' => 'Meals & Entertainment',
            'professional_services' => 'Professional Services',
            'inventory' => 'Inventory Purchase',
            'equipment' => 'Equipment & Tools',
            'rent' => 'Rent & Lease',
            'insurance' => 'Insurance',
            'taxes' => 'Taxes & Fees',
            'miscellaneous' => 'Miscellaneous'
        ];

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Expense categories retrieved successfully'
        ]);
    }

    /**
     * Get expense summary/statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Expense::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('expense_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $summary = [
            'total_expenses' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'average_amount' => $query->avg('amount'),
            'expenses_by_category' => (clone $query)
                ->select('category', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('category')
                ->get(),
            'expenses_by_user' => (clone $query)
                ->select('user_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->with('user:id,name')
                ->groupBy('user_id')
                ->get(),
            'recent_expenses' => (clone $query)
                ->with(['user:id,name,email'])
                ->orderBy('expense_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
            'message' => 'Expense summary retrieved successfully'
        ]);
    }

    /**
     * Store expense for a specific cash session.
     */
    public function storeForSession(Request $request, string $sessionId): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'receipt_number' => 'nullable|string|max:100',
            'vendor' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Validate cash session exists and is open
            $cashSession = CashSession::findOrFail($sessionId);
            
            if ($cashSession->status === 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot add expense to a closed cash session'
                ], 422);
            }

            // Verify session belongs to user's store
            if ($cashSession->store_id !== request()->user()->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this cash session'
                ], 403);
            }

            $expense = Expense::create([
                'store_id' => request()->user()->store_id,
                'user_id' => request()->user()->id,
                'cash_session_id' => $sessionId,
                'category' => $request->category,
                'description' => $request->description,
                'amount' => $request->amount,
                'receipt_number' => $request->receipt_number,
                'vendor' => $request->vendor,
                'expense_date' => now()->toDateString(),
                'notes' => $request->notes,
            ]);

            // Update cash session expected balance
            $cashSession->calculateExpectedBalance();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $expense->load('user:id,name,email'),
                'message' => 'Expense added successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add expense',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
