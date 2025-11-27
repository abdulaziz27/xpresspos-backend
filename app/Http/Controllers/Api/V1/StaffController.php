<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ChecksPlanLimits;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class StaffController extends Controller
{
    use ChecksPlanLimits;
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:owner']);
    }

    /**
     * Get authenticated user with fallback.
     */
    private function getAuthUser()
    {
        return auth()->user() ?? request()->user();
    }

    /**
     * Display a listing of staff members.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $user = auth()->user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        $staff = User::where('store_id', $user->store_id)
            ->where('id', '!=', $user->id)
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin_sistem');
            })
            ->with('roles', 'permissions')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $staff,
            'message' => 'Staff members retrieved successfully'
        ]);
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = auth()->user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Check staff limit before creating
        $tenant = $user->currentTenant();
        if ($tenant) {
            // Get current staff count for tenant via store_user_assignments
            // Count users that have assignments to stores belonging to this tenant
            $currentStaffCount = User::whereHas('storeAssignments', function ($q) use ($tenant) {
                $q->whereHas('store', function ($storeQuery) use ($tenant) {
                    $storeQuery->where('tenant_id', $tenant->id);
                });
            })
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'admin_sistem');
            })
            ->count();
            
            // Check if within limit
            $limitCheck = $this->canPerformAction($tenant, 'create_staff', $currentStaffCount);
            
            if (!$limitCheck['allowed']) {
                return $this->limitExceededResponse(
                    'staff members',
                    $currentStaffCount,
                    $limitCheck['limit'] ?? 0,
                    'Pro'
                );
            }
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', 'string', Rule::in(['manager', 'cashier'])],
        ]);

        $staff = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'store_id' => $user->store_id,
        ]);

        $staff->assignRole($request->role);

        return response()->json([
            'success' => true,
            'data' => $staff->load('roles', 'permissions'),
            'message' => 'Staff member created successfully'
        ], 201);
    }

    /**
     * Display the specified staff member.
     */
    public function show(User $staff): JsonResponse
    {
        $this->authorize('view', $staff);

        $user = auth()->user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure staff belongs to the same store
        if ($staff->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot access staff from different store'
                ]
            ], 403);
        }

        // Ensure it's not a system admin
        if ($staff->hasRole('admin_sistem')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot access system administrator'
                ]
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $staff->load('roles', 'permissions'),
            'message' => 'Staff member retrieved successfully'
        ]);
    }

    /**
     * Update the specified staff member.
     */
    public function update(Request $request, User $staff): JsonResponse
    {
        $this->authorize('update', $staff);

        $user = auth()->user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure staff belongs to the same store
        if ($staff->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot manage staff from different store'
                ]
            ], 403);
        }

        // Ensure it's not a system admin
        if ($staff->hasRole('admin_sistem')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot manage system administrator'
                ]
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($staff->id)],
            'password' => 'sometimes|required|string|min:8',
        ]);

        $staff->update($request->only(['name', 'email']));

        if ($request->has('password')) {
            $staff->update(['password' => bcrypt($request->password)]);
        }

        return response()->json([
            'success' => true,
            'data' => $staff->load('roles', 'permissions'),
            'message' => 'Staff member updated successfully'
        ]);
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(User $staff): JsonResponse
    {
        $this->authorize('delete', $staff);

        $user = auth()->user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure staff belongs to the same store
        if ($staff->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot delete staff from different store'
                ]
            ], 403);
        }

        // Ensure it's not a system admin
        if ($staff->hasRole('admin_sistem')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot delete system administrator'
                ]
            ], 403);
        }

        // Cannot delete self
        if ($staff->id === $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot delete yourself'
                ]
            ], 403);
        }

        $staff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff member deleted successfully'
        ]);
    }

    /**
     * Assign a role to staff member.
     */
    public function assignRole(User $staff, Request $request): JsonResponse
    {
        $user = auth()->user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure staff belongs to the same store
        if ($staff->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot manage staff from different store'
                ]
            ], 403);
        }

        $request->validate([
            'role' => ['required', 'string', Rule::in(['owner', 'manager', 'cashier'])],
        ]);

        $role = $request->input('role');

        // Cannot assign system admin role
        if ($role === 'admin_sistem') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot assign system admin role'
                ]
            ], 403);
        }

        // Cannot assign owner role to others (only one owner per store)
        if ($role === 'owner' && $staff->id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot assign owner role to other staff members'
                ]
            ], 403);
        }

        $staff->syncRoles([$role]);

        return response()->json([
            'success' => true,
            'data' => $staff->load('roles', 'permissions'),
            'message' => 'Role assigned successfully'
        ]);
    }

    /**
     * Remove a role from staff member.
     */
    public function removeRole(User $staff, Role $role): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure staff belongs to the same store
        if ($staff->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot manage staff from different store'
                ]
            ], 403);
        }

        // Cannot remove system admin role
        if ($role->name === 'admin_sistem') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot remove system admin role'
                ]
            ], 403);
        }

        // Cannot remove owner role from self
        if ($role->name === 'owner' && $staff->id === $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot remove owner role from yourself'
                ]
            ], 403);
        }

        $staff->removeRole($role);

        return response()->json([
            'success' => true,
            'data' => $staff->load('roles', 'permissions'),
            'message' => 'Role removed successfully'
        ]);
    }

    /**
     * Grant a permission to staff member.
     */
    public function grantPermission(User $staff, Request $request): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure staff belongs to the same store
        if ($staff->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot manage staff from different store'
                ]
            ], 403);
        }

        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        $permission = $request->input('permission');

        // Cannot grant system permissions
        if (str_starts_with($permission, 'system.') || str_starts_with($permission, 'subscription.')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot grant system-level permissions'
                ]
            ], 403);
        }

        $staff->givePermissionTo($permission);

        return response()->json([
            'success' => true,
            'data' => $staff->load('roles', 'permissions'),
            'message' => 'Permission granted successfully'
        ]);
    }

    /**
     * Revoke a permission from staff member.
     */
    public function revokePermission(User $staff, Permission $permission): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure staff belongs to the same store
        if ($staff->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot manage staff from different store'
                ]
            ], 403);
        }

        $staff->revokePermissionTo($permission);

        return response()->json([
            'success' => true,
            'data' => $staff->load('roles', 'permissions'),
            'message' => 'Permission revoked successfully'
        ]);
    }

    /**
     * Get available roles for assignment.
     */
    public function availableRoles(): JsonResponse
    {
        $roles = Role::whereNotIn('name', ['admin_sistem'])->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
            'message' => 'Available roles retrieved successfully'
        ]);
    }

    /**
     * Get available permissions for assignment.
     */
    public function availablePermissions(): JsonResponse
    {
        $permissions = Permission::where('name', 'not like', 'system.%')
            ->where('name', 'not like', 'subscription.%')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'message' => 'Available permissions retrieved successfully'
        ]);
    }

    /**
     * Send staff invitation.
     */
    public function invite(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Check staff limit before inviting
        $tenant = $user->currentTenant();
        if ($tenant) {
            // Get current staff count for tenant via store_user_assignments
            $currentStaffCount = User::whereHas('storeAssignments', function ($q) use ($tenant) {
                $q->whereHas('store', function ($storeQuery) use ($tenant) {
                    $storeQuery->where('tenant_id', $tenant->id);
                });
            })
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'admin_sistem');
            })
            ->count();
            
            // Also count pending invitations for all stores in this tenant
            $storeIds = \App\Models\Store::where('tenant_id', $tenant->id)->pluck('id');
            $pendingInvitations = \App\Models\StaffInvitation::whereIn('store_id', $storeIds)
                ->where('status', 'pending')
                ->count();
            
            $totalStaffCount = $currentStaffCount + $pendingInvitations;
            
            // Check if within limit
            $limitCheck = $this->canPerformAction($tenant, 'create_staff', $totalStaffCount);
            
            if (!$limitCheck['allowed']) {
                return $this->limitExceededResponse(
                    'staff members',
                    $totalStaffCount,
                    $limitCheck['limit'] ?? 0,
                    'Pro'
                );
            }
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users|unique:staff_invitations,email,NULL,id,status,pending',
            'role' => ['required', 'string', Rule::in(['manager', 'cashier'])],
            'expires_in_days' => 'sometimes|integer|min:1|max:30',
        ]);

        $invitation = \App\Models\StaffInvitation::create([
            'store_id' => $user->store_id,
            'invited_by' => $user->id,
            'email' => $request->email,
            'name' => $request->name,
            'role' => $request->role,
            'expires_at' => now()->addDays($request->input('expires_in_days', 7)),
            'metadata' => [
                'invited_at' => now()->toISOString(),
                'invitation_source' => 'api',
            ],
        ]);

        // Log the invitation activity
        \App\Models\ActivityLog::create([
            'store_id' => $user->store_id,
            'user_id' => $user->id,
            'event' => 'staff.invitation.sent',
            'auditable_type' => \App\Models\StaffInvitation::class,
            'auditable_id' => $invitation->id,
            'new_values' => [
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $invitation->load('invitedBy'),
            'message' => 'Staff invitation sent successfully'
        ], 201);
    }

    /**
     * Get pending invitations.
     */
    public function invitations(): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        $invitations = \App\Models\StaffInvitation::where('store_id', $user->store_id)
            ->with('invitedBy', 'user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invitations,
            'message' => 'Staff invitations retrieved successfully'
        ]);
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancelInvitation(\App\Models\StaffInvitation $invitation): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure invitation belongs to the same store
        if ($invitation->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot cancel invitation from different store'
                ]
            ], 403);
        }

        if (!$invitation->isPending()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATUS',
                    'message' => 'Can only cancel pending invitations'
                ]
            ], 400);
        }

        $invitation->markAsCancelled();

        // Log the cancellation activity
        \App\Models\ActivityLog::create([
            'store_id' => $user->store_id,
            'user_id' => $user->id,
            'event' => 'staff.invitation.cancelled',
            'auditable_type' => \App\Models\StaffInvitation::class,
            'auditable_id' => $invitation->id,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'cancelled'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation cancelled successfully'
        ]);
    }

    /**
     * Resend a pending invitation.
     */
    public function resendInvitation(\App\Models\StaffInvitation $invitation): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure invitation belongs to the same store
        if ($invitation->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot resend invitation from different store'
                ]
            ], 403);
        }

        if (!$invitation->isPending() && $invitation->status !== 'expired') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATUS',
                    'message' => 'Can only resend pending or expired invitations'
                ]
            ], 400);
        }

        // Update invitation with new token and expiry
        $invitation->update([
            'token' => \Illuminate\Support\Str::random(64),
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        // Log the resend activity
        \App\Models\ActivityLog::create([
            'store_id' => $user->store_id,
            'user_id' => $user->id,
            'event' => 'staff.invitation.resent',
            'auditable_type' => \App\Models\StaffInvitation::class,
            'auditable_id' => $invitation->id,
            'new_values' => [
                'expires_at' => $invitation->expires_at->toISOString(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $invitation->fresh()->load('invitedBy'),
            'message' => 'Invitation resent successfully'
        ]);
    }

    /**
     * Get staff activity logs.
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'event' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = \App\Models\ActivityLog::where('store_id', $user->store_id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('event')) {
            $query->where('event', 'like', '%' . $request->event . '%');
        }

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
            'message' => 'Activity logs retrieved successfully'
        ]);
    }

    /**
     * Get staff performance metrics.
     */
    public function performance(Request $request, User $staff = null): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'period' => 'sometimes|in:daily,weekly,monthly',
        ]);

        $storeId = $user->store_id;
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $query = \App\Models\StaffPerformance::where('store_id', $storeId)
            ->dateRange($startDate, $endDate)
            ->with('user');

        if ($staff) {
            // Ensure staff belongs to the same store
            if ($staff->store_id !== $storeId) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Cannot access performance data from different store'
                    ]
                ], 403);
            }
            $query->forUser($staff->id);
        }

        $performances = $query->get();

        // Calculate summary statistics
        $summary = [
            'total_orders' => $performances->sum('orders_processed'),
            'total_sales' => $performances->sum('total_sales'),
            'total_refunds' => $performances->sum('refunds_processed'),
            'total_refund_amount' => $performances->sum('refund_amount'),
            'total_hours' => $performances->sum('hours_worked'),
            'average_order_value' => $performances->avg('average_order_value'),
            'average_sales_per_hour' => $performances->avg('sales_per_hour'),
            'average_satisfaction' => $performances->avg('customer_satisfaction_score'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'performances' => $performances,
                'summary' => $summary,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
            'message' => 'Staff performance data retrieved successfully'
        ]);
    }

    /**
     * Update staff performance metrics.
     */
    public function updatePerformance(Request $request, User $staff): JsonResponse
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        // Ensure staff belongs to the same store
        if ($staff->store_id !== $user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cannot update performance data from different store'
                ]
            ], 403);
        }

        $request->validate([
            'date' => 'required|date',
            'orders_processed' => 'sometimes|integer|min:0',
            'total_sales' => 'sometimes|numeric|min:0',
            'refunds_processed' => 'sometimes|integer|min:0',
            'refund_amount' => 'sometimes|numeric|min:0',
            'hours_worked' => 'sometimes|integer|min:0|max:24',
            'customer_interactions' => 'sometimes|integer|min:0',
            'customer_satisfaction_score' => 'sometimes|numeric|min:0|max:5',
            'additional_metrics' => 'sometimes|array',
        ]);

        $performance = \App\Models\StaffPerformance::updateOrCreate(
            [
                'store_id' => $user->store_id,
                'user_id' => $staff->id,
                'date' => $request->date,
            ],
            $request->only([
                'orders_processed',
                'total_sales',
                'refunds_processed',
                'refund_amount',
                'hours_worked',
                'customer_interactions',
                'customer_satisfaction_score',
                'additional_metrics',
            ])
        );

        // Recalculate derived metrics
        $performance->calculateMetrics();

        // Log the performance update
        \App\Models\ActivityLog::create([
            'store_id' => $user->store_id,
            'user_id' => $user->id,
            'event' => 'staff.performance.updated',
            'auditable_type' => \App\Models\StaffPerformance::class,
            'auditable_id' => $performance->id,
            'new_values' => $performance->only([
                'date',
                'orders_processed',
                'total_sales',
                'hours_worked'
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $performance->load('user'),
            'message' => 'Staff performance updated successfully'
        ]);
    }
}
