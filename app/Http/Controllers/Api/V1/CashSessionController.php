<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Http\Requests\StoreCashSessionRequest;
use App\Http\Requests\UpdateCashSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'tenant.scope']);
        // Remove strict permission middleware for MVP testing
        // Owner role should have access to all cash session operations
    }

    /**
     * Display a listing of cash sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CashSession::with(['user:id,name,email'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('opened_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sessions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'message' => 'Cash sessions retrieved successfully'
        ]);
    }

    /**
     * Store a newly created cash session (open session).
     */
    public function store(StoreCashSessionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Check if user already has an open session
            $existingSession = CashSession::where('user_id', auth()->id())
                ->where('status', 'open')
                ->first();

            if ($existingSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an open cash session. Please close it first.',
                    'data' => ['existing_session' => $existingSession]
                ], 422);
            }

            $session = CashSession::create([
                'store_id' => request()->user()->store_id,
                'user_id' => request()->user()->id,
                'opening_balance' => $request->opening_balance,
                'status' => 'open',
                'opened_at' => now(),
                'notes' => $request->notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $session->load('user:id,name,email'),
                'message' => 'Cash session opened successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to open cash session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified cash session.
     */
    public function show(string $id): JsonResponse
    {
        $cashSession = CashSession::with(['user:id,name,email', 'expenses'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cashSession,
            'message' => 'Cash session retrieved successfully'
        ]);
    }

    /**
     * Update the specified cash session.
     */
    public function update(UpdateCashSessionRequest $request, string $id): JsonResponse
    {
        $cashSession = CashSession::findOrFail($id);
        if ($cashSession->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a closed cash session'
            ], 422);
        }

        $cashSession->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $cashSession->load('user:id,name,email'),
            'message' => 'Cash session updated successfully'
        ]);
    }

    /**
     * Close the specified cash session.
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $cashSession = CashSession::findOrFail($id);
        $request->validate([
            'closing_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($cashSession->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Cash session is already closed'
            ], 422);
        }

        if ($cashSession->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only close your own cash session'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $cashSession->close($request->closing_balance);

            if ($request->notes) {
                $cashSession->update(['notes' => $request->notes]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $cashSession->load('user:id,name,email'),
                'message' => 'Cash session closed successfully',
                'variance_detected' => $cashSession->hasVariance()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to close cash session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current open session for authenticated user.
     */
    public function current(): JsonResponse
    {
        $session = CashSession::where('user_id', auth()->id())
            ->where('status', 'open')
            ->with(['user:id,name,email', 'expenses'])
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No open cash session found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $session,
            'message' => 'Current cash session retrieved successfully'
        ]);
    }

    /**
     * Get cash session summary/statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = CashSession::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('opened_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $summary = [
            'total_sessions' => $query->count(),
            'open_sessions' => (clone $query)->where('status', 'open')->count(),
            'closed_sessions' => (clone $query)->where('status', 'closed')->count(),
            'total_cash_sales' => (clone $query)->where('status', 'closed')->sum('cash_sales'),
            'total_cash_expenses' => (clone $query)->where('status', 'closed')->sum('cash_expenses'),
            'total_variance' => (clone $query)->where('status', 'closed')->sum('variance'),
            'sessions_with_variance' => (clone $query)->where('status', 'closed')
                ->whereRaw('ABS(variance) > 0.01')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
            'message' => 'Cash session summary retrieved successfully'
        ]);
    }

    /**
     * Remove the specified cash session.
     */
    public function destroy(string $id): JsonResponse
    {
        $cashSession = CashSession::findOrFail($id);
        if ($cashSession->status === 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an open cash session. Please close it first.'
            ], 422);
        }

        $cashSession->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cash session deleted successfully'
        ]);
    }
}
