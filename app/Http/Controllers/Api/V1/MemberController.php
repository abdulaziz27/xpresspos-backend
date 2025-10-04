<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Models\MemberTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberController extends Controller
{
    /**
     * Display a listing of members.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Member::class);

        $query = Member::query();

        // Apply filters
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('loyalty_points_min')) {
            $query->where('loyalty_points', '>=', $request->input('loyalty_points_min'));
        }

        if ($request->filled('total_spent_min')) {
            $query->where('total_spent', '>=', $request->input('total_spent_min'));
        }

        if ($request->filled('visit_count_min')) {
            $query->where('visit_count', '>=', $request->input('visit_count_min'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('member_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        
        if (in_array($sortBy, ['name', 'member_number', 'loyalty_points', 'total_spent', 'visit_count', 'last_visit_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $members = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => MemberResource::collection($members->items()),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Store a newly created member.
     */
    public function store(StoreMemberRequest $request): JsonResponse
    {
        $this->authorize('create', Member::class);

        try {
            DB::beginTransaction();

            $member = Member::create([
                'store_id' => auth()->user()->store_id,
                'member_number' => $this->generateMemberNumber(),
                ...$request->validated()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new MemberResource($member),
                'message' => 'Member created successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Member creation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MEMBER_CREATION_FAILED',
                    'message' => 'Failed to create member. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Display the specified member.
     */
    public function show(string $id): JsonResponse
    {
        $member = Member::with(['tier', 'loyaltyTransactions' => function ($query) {
            $query->latest()->limit(10);
        }])->findOrFail($id);
        $this->authorize('view', $member);

        $member->load(['orders' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => new MemberResource($member),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Update the specified member.
     */
    public function update(UpdateMemberRequest $request, string $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $this->authorize('update', $member);

        try {
            DB::beginTransaction();

            $member->update($request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new MemberResource($member->fresh()),
                'message' => 'Member updated successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Member update failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MEMBER_UPDATE_FAILED',
                    'message' => 'Failed to update member. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Remove the specified member.
     */
    public function destroy(string $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $this->authorize('delete', $member);

        try {
            DB::beginTransaction();

            // Soft delete by marking as inactive instead of hard delete
            $member->update(['is_active' => false]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Member deactivated successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Member deactivation failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MEMBER_DEACTIVATION_FAILED',
                    'message' => 'Failed to deactivate member. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Add loyalty points to a member.
     */
    public function addLoyaltyPoints(Request $request, string $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $this->authorize('update', $member);

        $request->validate([
            'points' => 'required|integer|min:1|max:10000',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $member->addLoyaltyPoints(
                $request->input('points'),
                $request->input('reason')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new MemberResource($member->fresh()),
                'message' => "Added {$request->input('points')} loyalty points successfully",
                'meta' => [
                    'points_added' => $request->input('points'),
                    'new_balance' => $member->fresh()->loyalty_points,
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Adding loyalty points failed', [
                'member_id' => $member->id,
                'points' => $request->input('points'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOYALTY_POINTS_ADD_FAILED',
                    'message' => 'Failed to add loyalty points. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Redeem loyalty points from a member.
     */
    public function redeemLoyaltyPoints(Request $request, string $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $this->authorize('update', $member);

        $request->validate([
            'points' => 'required|integer|min:1|max:10000',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $success = $member->redeemLoyaltyPoints($request->input('points'));

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INSUFFICIENT_POINTS',
                        'message' => 'Insufficient loyalty points for redemption.',
                    ],
                    'meta' => [
                        'available_points' => $member->loyalty_points,
                        'requested_points' => $request->input('points'),
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1'
                    ]
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new MemberResource($member->fresh()),
                'message' => "Redeemed {$request->input('points')} loyalty points successfully",
                'meta' => [
                    'points_redeemed' => $request->input('points'),
                    'new_balance' => $member->fresh()->loyalty_points,
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Redeeming loyalty points failed', [
                'member_id' => $member->id,
                'points' => $request->input('points'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOYALTY_POINTS_REDEEM_FAILED',
                    'message' => 'Failed to redeem loyalty points. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Get member statistics.
     */
    public function statistics(string $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $this->authorize('view', $member);

        $member->load(['tier', 'loyaltyTransactions' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $activitySummary = $member->getActivitySummary(30);

        $stats = [
            'total_orders' => $member->orders()->count(),
            'completed_orders' => $member->orders()->completed()->count(),
            'average_order_value' => $member->orders()->completed()->avg('total_amount') ?? 0,
            'last_order_date' => $member->orders()->latest()->first()?->created_at,
            'current_tier' => $member->tier ? [
                'id' => $member->tier->id,
                'name' => $member->tier->name,
                'color' => $member->tier->color,
                'discount_percentage' => $member->tier->discount_percentage,
                'benefits' => $member->tier->benefits,
            ] : null,
            'points_to_next_tier' => $member->getPointsToNextTier(),
            'tier_discount_percentage' => $member->getTierDiscountPercentage(),
            'activity_summary' => $activitySummary,
            'recent_transactions' => $member->loyaltyTransactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'points' => $transaction->points,
                    'reason' => $transaction->reason,
                    'created_at' => $transaction->created_at->toISOString(),
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get member's loyalty point transaction history.
     */
    public function loyaltyHistory(Request $request, string $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $this->authorize('view', $member);

        $query = $member->loyaltyTransactions()->with(['order', 'user']);

        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        
        if (in_array($sortBy, ['created_at', 'points', 'type'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Adjust member loyalty points (manual adjustment by staff).
     */
    public function adjustLoyaltyPoints(Request $request, string $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $this->authorize('update', $member);

        $request->validate([
            'points' => 'required|integer|min:-999999|max:999999',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $transaction = $member->adjustLoyaltyPoints(
                $request->input('points'),
                $request->input('reason'),
                [
                    'description' => $request->input('description'),
                    'adjusted_by' => auth()->user()->name,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'member' => new MemberResource($member->fresh()),
                    'transaction' => $transaction,
                ],
                'message' => "Loyalty points adjusted successfully",
                'meta' => [
                    'points_adjusted' => $request->input('points'),
                    'new_balance' => $member->fresh()->loyalty_points,
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Adjusting loyalty points failed', [
                'member_id' => $member->id,
                'points' => $request->input('points'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOYALTY_POINTS_ADJUST_FAILED',
                    'message' => 'Failed to adjust loyalty points. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Get available member tiers.
     */
    public function tiers(): JsonResponse
    {
        $this->authorize('viewAny', Member::class);

        $tiers = MemberTier::where('store_id', auth()->user()->store_id)
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tiers,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get member tier statistics.
     */
    public function tierStatistics(): JsonResponse
    {
        $this->authorize('viewAny', Member::class);

        $loyaltyService = app(\App\Services\LoyaltyService::class);
        $statistics = $loyaltyService->getTierStatistics(auth()->user()->store_id);

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Generate unique member number.
     */
    private function generateMemberNumber(): string
    {
        $prefix = 'MEM';
        $date = now()->format('Ymd');
        $sequence = Member::whereDate('created_at', now())->count() + 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get loyalty tier based on points.
     */
    private function getLoyaltyTier(int $points): string
    {
        return match (true) {
            $points >= 10000 => 'Platinum',
            $points >= 5000 => 'Gold',
            $points >= 1000 => 'Silver',
            default => 'Bronze',
        };
    }

    /**
     * Get points needed for next tier.
     */
    private function getPointsToNextTier(int $points): int
    {
        return match (true) {
            $points >= 10000 => 0,
            $points >= 5000 => 10000 - $points,
            $points >= 1000 => 5000 - $points,
            default => 1000 - $points,
        };
    }
}