<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreUserAssignmentResource;
use App\Enums\AssignmentRoleEnum;
use App\Models\StoreUserAssignment;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class StoreUserAssignmentController extends Controller
{
    /**
     * Get all assignments for a store.
     */
    public function index(Request $request, string $storeId): JsonResponse
    {
        $this->authorize('viewAny', StoreUserAssignment::class);

        $assignments = StoreUserAssignment::with(['user', 'store'])
            ->where('store_id', $storeId)
            ->get()
            ->filter(function ($assignment) {
                return $this->authorize('view', $assignment);
            });

        return response()->json([
            'success' => true,
            'data' => StoreUserAssignmentResource::collection($assignments)
        ]);
    }

    /**
     * Assign a user to a store.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StoreUserAssignment::class);
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'user_id' => 'required|exists:users,id',
            'assignment_role' => ['required', 'string', Rule::in(AssignmentRoleEnum::values())],
            'is_primary' => 'boolean'
        ]);

        // Check if assignment already exists
        $existing = StoreUserAssignment::where('store_id', $validated['store_id'])
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'User is already assigned to this store'
            ], 409);
        }

        // If setting as primary, remove primary from other assignments for this user
        if ($validated['is_primary'] ?? false) {
            StoreUserAssignment::where('user_id', $validated['user_id'])
                ->update(['is_primary' => false]);
        }

        $assignment = StoreUserAssignment::create($validated);
        $assignment->load(['user', 'store']);

        return response()->json([
            'success' => true,
            'data' => new StoreUserAssignmentResource($assignment),
            'message' => 'User assigned to store successfully'
        ], 201);
    }

    /**
     * Update a store assignment.
     */
    public function update(Request $request, StoreUserAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);
        $validated = $request->validate([
            'assignment_role' => ['sometimes', 'string', Rule::in(AssignmentRoleEnum::values())],
            'is_primary' => 'sometimes|boolean'
        ]);

        // If setting as primary, remove primary from other assignments for this user
        if (isset($validated['is_primary']) && $validated['is_primary']) {
            StoreUserAssignment::where('user_id', $assignment->user_id)
                ->where('id', '!=', $assignment->id)
                ->update(['is_primary' => false]);
        }

        $assignment->update($validated);
        $assignment->load(['user', 'store']);

        return response()->json([
            'success' => true,
            'data' => new StoreUserAssignmentResource($assignment),
            'message' => 'Assignment updated successfully'
        ]);
    }

    /**
     * Remove a user from a store.
     */
    public function destroy(StoreUserAssignment $assignment): JsonResponse
    {
        $this->authorize('delete', $assignment);
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'User removed from store successfully'
        ]);
    }

    /**
     * Get all stores for a user.
     */
    public function userStores(Request $request, User $user): JsonResponse
    {
        $assignments = $user->storeAssignments()
            ->with('store')
            ->get();

        return response()->json([
            'success' => true,
            'data' => StoreUserAssignmentResource::collection($assignments)
        ]);
    }

    /**
     * Set primary store for a user.
     */
    public function setPrimaryStore(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id'
        ]);

        // Check if user has access to this store
        $assignment = $user->storeAssignments()
            ->where('store_id', $validated['store_id'])
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'User is not assigned to this store'
            ], 403);
        }

        // Remove primary from all other assignments
        $user->storeAssignments()->update(['is_primary' => false]);

        // Set this assignment as primary
        $assignment->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Primary store updated successfully'
        ]);
    }
}