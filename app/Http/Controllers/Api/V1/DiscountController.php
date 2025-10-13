<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\UpdateDiscountRequest;
use App\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    /**
     * Display a listing of the discounts.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Discount::class);

        $query = Discount::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('only_active')) {
            $query->active()->notExpired();
        } else {
            if ($request->filled('status') && in_array($request->input('status'), [Discount::STATUS_ACTIVE, Discount::STATUS_INACTIVE], true)) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('type') && in_array($request->input('type'), [Discount::TYPE_PERCENTAGE, Discount::TYPE_FIXED], true)) {
                $query->where('type', $request->input('type'));
            }

            if ($request->has('is_expired')) {
                if ($request->boolean('is_expired')) {
                    $query->whereNotNull('expired_date')
                        ->where('expired_date', '<', now()->toDateString());
                } else {
                    $query->where(function ($builder) {
                        $builder->whereNull('expired_date')
                            ->orWhere('expired_date', '>=', now()->toDateString());
                    });
                }
            }
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (!in_array($sortBy, ['name', 'value', 'status', 'expired_date', 'created_at', 'updated_at'], true)) {
            $sortBy = 'created_at';
        }

        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortBy, $sortDirection);

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $discounts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $discounts->items(),
            'meta' => [
                'current_page' => $discounts->currentPage(),
                'last_page' => $discounts->lastPage(),
                'per_page' => $discounts->perPage(),
                'total' => $discounts->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'applied_filters' => $request->only([
                    'search',
                    'status',
                    'type',
                    'only_active',
                    'is_expired',
                    'sort_by',
                    'sort_direction',
                ]),
            ],
        ]);
    }

    /**
     * Store a newly created discount.
     */
    public function store(StoreDiscountRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $storeId = $request->user()->hasRole('admin_sistem')
            ? $validated['store_id']
            : $request->user()->store_id;

        $discount = Discount::create([
            'store_id' => $storeId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'status' => $validated['status'] ?? Discount::STATUS_ACTIVE,
            'expired_date' => $validated['expired_date'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $discount,
            'message' => 'Discount created successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ], 201);
    }

    /**
     * Display the specified discount.
     */
    public function show(Discount $discount): JsonResponse
    {
        $this->authorize('view', $discount);

        return response()->json([
            'success' => true,
            'data' => $discount,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ]);
    }

    /**
     * Update the specified discount.
     */
    public function update(UpdateDiscountRequest $request, Discount $discount): JsonResponse
    {
        $validated = $request->validated();

        $discount->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'status' => $validated['status'],
            'expired_date' => $validated['expired_date'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $discount->fresh(),
            'message' => 'Discount updated successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ]);
    }

    /**
     * Remove the specified discount.
     */
    public function destroy(Discount $discount): JsonResponse
    {
        $this->authorize('delete', $discount);

        $discount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Discount deleted successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ]);
    }
}
