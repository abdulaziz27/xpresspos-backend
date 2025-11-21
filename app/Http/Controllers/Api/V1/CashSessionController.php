<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Http\Requests\StoreCashSessionRequest;
use App\Http\Requests\UpdateCashSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\StoreContext;

class CashSessionController extends Controller
{
    public function __construct()
    {
        // Note: auth:sanctum is already applied in routes/api.php
        // Only apply tenant.scope here to avoid duplicate auth checks
        $this->middleware('tenant.scope');
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
            $existingSession = CashSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->first();

            if ($existingSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an open cash session. Please close it first.',
                    'data' => ['existing_session' => $existingSession]
                ], 422);
            }

            // User is already authenticated by auth:sanctum middleware
            $user = $request->user();
            
            $session = CashSession::create([
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'opening_balance' => $request->opening_balance,
                'status' => 'open',
                'opened_at' => now(),
                'notes' => $request->notes,
            ]);

            // Calculate initial expected balance (will be opening_balance + 0 sales - 0 expenses)
            $session->calculateExpectedBalance();
            $session->refresh();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $session->load(['user:id,name,email', 'expenses']),
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
    public function show(Request $request, string $id): JsonResponse
    {
        $cashSession = CashSession::with(['user:id,name,email', 'expenses'])->find($id);

        // If not found, try without store scope (in case of store context issue)
        if (!$cashSession) {
            $cashSession = CashSession::withoutGlobalScopes()
                ->with(['user:id,name,email', 'expenses'])
                ->where('id', $id)
                ->where('store_id', $request->user()->store_id)
                ->first();

            if ($cashSession && !$this->ensureStoreContextForSession($cashSession)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this cash session.'
                ], 403);
            }
        }

        if (!$cashSession) {
            return response()->json([
                'success' => false,
                'message' => 'Cash session not found'
            ], 404);
        }

        // Always calculate expected balance to ensure it's up-to-date
        if ($cashSession->status === 'open') {
            $cashSession->calculateExpectedBalance();
            $cashSession->refresh();
        }

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
        // User is already authenticated by auth:sanctum middleware in routes/api.php
        // $request->user() is automatically set by Sanctum after authentication
        // If user is null here, it means middleware didn't run properly
        $user = $request->user() ?? Auth::user();

        if (!$user) {
            // This should not happen if middleware is working correctly
            // But if it does, return proper error response
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please ensure you are sending a valid Bearer token in the Authorization header.',
                'hint' => 'Format: Authorization: Bearer {your_token}'
            ], 401);
        }

        $cashSession = CashSession::with('user:id,name,email')->find($id);

        if (!$cashSession) {
            $cashSession = CashSession::withoutGlobalScopes()
                ->with('user:id,name,email')
                ->find($id);

            if ($cashSession && !$this->ensureStoreContextForSession($cashSession)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this cash session.'
                ], 403);
            }
        }

        if (!$cashSession) {
            return response()->json([
                'success' => false,
                'message' => 'Cash session not found'
            ], 404);
        }

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

        // Use strict comparison and ensure both are same type (int)
        $sessionUserId = (int) $cashSession->user_id;
        $currentUserId = (int) $user->id;
        
        if ($sessionUserId !== $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You can only close your own cash session',
                'debug' => config('app.debug') ? [
                    'session_user_id' => $cashSession->user_id,
                    'current_user_id' => $user->id,
                    'session_user_id_type' => gettype($cashSession->user_id),
                    'current_user_id_type' => gettype($user->id),
                ] : null
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
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Try to find session with store scope first
        $session = CashSession::with(['user:id,name,email', 'expenses'])
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        // If not found, try without store scope (in case of store context issue)
        if (!$session) {
            $session = CashSession::withoutGlobalScopes()
                ->with(['user:id,name,email', 'expenses'])
                ->where('user_id', $user->id)
                ->where('store_id', $user->store_id)
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();

            if ($session && !$this->ensureStoreContextForSession($session)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to restore store context for the open cash session.'
                ], 403);
            }
        }

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No open cash session found',
                'hint' => 'Please create a new cash session first using POST /api/v1/cash-sessions'
            ], 404);
        }

        // Always calculate expected balance to ensure it's up-to-date
        // This recalculates cash_sales and cash_expenses based on current data
        // This is important because cash_sales can change as new payments are made
        // and cash_expenses can change as expenses are added
        $session->calculateExpectedBalance();
        $session->refresh();

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

    /**
     * Restore store context if necessary for the provided cash session.
     */
    protected function ensureStoreContextForSession(CashSession $session): bool
    {
        // Try to get user from request first, fallback to Auth
        $user = request()->user() ?: Auth::user();

        if (!$user) {
            return false;
        }

        $storeContext = StoreContext::instance();
        $currentStoreId = $storeContext->current($user);

        if ($currentStoreId === $session->store_id) {
            return true;
        }

        $hasAccess = $user->hasRole('admin_sistem') || $user->store_id === $session->store_id;

        if (!$hasAccess) {
            if ($user->relationLoaded('stores')) {
                $hasAccess = $user->stores->contains('id', $session->store_id);
            } else {
                $hasAccess = $user->stores()
                    ->where('stores.id', $session->store_id)
                    ->exists();
            }
        }

        if (!$hasAccess) {
            return false;
        }

        if ($user->hasRole('admin_sistem')) {
            $storeContext->set($session->store_id);
        } else {
            $storeContext->setForUser($user, $session->store_id);
        }

        $user->setAttribute('store_id', $session->store_id);

        if (function_exists('setPermissionsTeamId')) {
            // Use tenant_id from store for permissions
            if ($session->store && $session->store->tenant_id) {
                setPermissionsTeamId($session->store->tenant_id);
            } else {
                $tenantId = $user->currentTenantId();
                if ($tenantId) {
                    setPermissionsTeamId($tenantId);
                }
            }
        }

        return true;
    }
}
