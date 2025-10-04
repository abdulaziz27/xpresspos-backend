<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Http\Resources\TableResource;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TableController extends Controller
{
    /**
     * Display a listing of tables.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Table::class);

        $query = Table::query();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->input('location') . '%');
        }

        if ($request->filled('capacity_min')) {
            $query->where('capacity', '>=', $request->input('capacity_min'));
        }

        if ($request->filled('capacity_max')) {
            $query->where('capacity', '<=', $request->input('capacity_max'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('table_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'table_number');
        $sortDirection = $request->input('sort_direction', 'asc');
        
        if (in_array($sortBy, ['table_number', 'name', 'capacity', 'status', 'location'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('table_number', 'asc');
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $tables = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => TableResource::collection($tables->items()),
            'meta' => [
                'current_page' => $tables->currentPage(),
                'last_page' => $tables->lastPage(),
                'per_page' => $tables->perPage(),
                'total' => $tables->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Store a newly created table.
     */
    public function store(StoreTableRequest $request): JsonResponse
    {
        $this->authorize('create', Table::class);

        try {
            DB::beginTransaction();

            $table = Table::create([
                'store_id' => auth()->user()->store_id,
                ...$request->validated()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new TableResource($table),
                'message' => 'Table created successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Table creation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TABLE_CREATION_FAILED',
                    'message' => 'Failed to create table. Please try again.',
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
     * Display the specified table.
     */
    public function show(string $id): JsonResponse
    {
        $table = Table::with(['currentOrder', 'occupancyHistories' => function ($query) {
            $query->latest()->limit(10);
        }])->findOrFail($id);
        $this->authorize('view', $table);

        $table->load(['currentOrder']);

        return response()->json([
            'success' => true,
            'data' => new TableResource($table),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Update the specified table.
     */
    public function update(UpdateTableRequest $request, string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $this->authorize('update', $table);

        try {
            DB::beginTransaction();

            $table->update($request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new TableResource($table->fresh()),
                'message' => 'Table updated successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Table update failed', [
                'table_id' => $table->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TABLE_UPDATE_FAILED',
                    'message' => 'Failed to update table. Please try again.',
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
     * Remove the specified table.
     */
    public function destroy(string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $this->authorize('delete', $table);

        if ($table->isOccupied()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TABLE_OCCUPIED',
                    'message' => 'Cannot delete an occupied table.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $table->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Table deleted successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Table deletion failed', [
                'table_id' => $table->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TABLE_DELETION_FAILED',
                    'message' => 'Failed to delete table. Please try again.',
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
     * Get available tables.
     */
    public function available(): JsonResponse
    {
        $this->authorize('viewAny', Table::class);

        $tables = Table::available()->orderBy('table_number')->get();

        return response()->json([
            'success' => true,
            'data' => TableResource::collection($tables),
            'meta' => [
                'total_available' => $tables->count(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Occupy a table.
     */
    public function occupy(Request $request, string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $this->authorize('update', $table);

        $request->validate([
            'order_id' => 'nullable|uuid|exists:orders,id',
            'party_size' => 'nullable|integer|min:1|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        if (!$table->isAvailable()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TABLE_NOT_AVAILABLE',
                    'message' => 'Table is not available for occupation.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order = $request->input('order_id') ? Order::find($request->input('order_id')) : null;
            $occupancy = $table->occupy($order, $request->input('party_size'));

            if ($request->filled('notes')) {
                $occupancy->update(['notes' => $request->input('notes')]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new TableResource($table->fresh()->load(['currentOrder', 'currentOccupancyRelation'])),
                'message' => 'Table occupied successfully',
                'meta' => [
                    'occupancy_id' => $occupancy->id,
                    'occupied_at' => $occupancy->occupied_at->toISOString(),
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Table occupation failed', [
                'table_id' => $table->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TABLE_OCCUPATION_FAILED',
                    'message' => 'Failed to occupy table. Please try again.',
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
     * Make a table available.
     */
    public function makeAvailable(string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $this->authorize('update', $table);

        try {
            DB::beginTransaction();

            $currentOccupancy = $table->currentOccupancy();
            $table->makeAvailable();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new TableResource($table->fresh()),
                'message' => 'Table made available successfully',
                'meta' => [
                    'cleared_at' => now()->toISOString(),
                    'occupancy_duration' => $currentOccupancy?->getFormattedDuration(),
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Making table available failed', [
                'table_id' => $table->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TABLE_AVAILABILITY_FAILED',
                    'message' => 'Failed to make table available. Please try again.',
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
     * Get table occupancy statistics.
     */
    public function occupancyStats(Request $request, Table $table): JsonResponse
    {
        $this->authorize('view', $table);

        $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        $days = $request->input('days', 30);
        $stats = $table->getOccupancyStats($days);

        return response()->json([
            'success' => true,
            'data' => [
                'table' => new TableResource($table),
                'period_days' => $days,
                'statistics' => $stats,
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get occupancy history for a table.
     */
    public function occupancyHistory(Request $request, Table $table): JsonResponse
    {
        $this->authorize('view', $table);

        $request->validate([
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'status' => 'sometimes|string|in:occupied,cleared,abandoned',
        ]);

        $query = $table->occupancyHistories()->with(['order', 'user']);

        // Apply filters
        if ($request->filled('date_from')) {
            $query->where('occupied_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('occupied_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply sorting
        $query->orderBy('occupied_at', 'desc');

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $histories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $histories->items(),
            'meta' => [
                'current_page' => $histories->currentPage(),
                'last_page' => $histories->lastPage(),
                'per_page' => $histories->perPage(),
                'total' => $histories->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get overall table occupancy report.
     */
    public function occupancyReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Table::class);

        $request->validate([
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'location' => 'sometimes|string',
        ]);

        $query = Table::where('store_id', auth()->user()->store_id)->active();

        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->input('location') . '%');
        }

        $tables = $query->with(['occupancyHistories' => function ($q) use ($request) {
            if ($request->filled('date_from')) {
                $q->where('occupied_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $q->where('occupied_at', '<=', $request->input('date_to'));
            }
        }])->get();

        $report = [
            'summary' => [
                'total_tables' => $tables->count(),
                'occupied_tables' => $tables->where('status', 'occupied')->count(),
                'available_tables' => $tables->where('status', 'available')->count(),
                'maintenance_tables' => $tables->where('status', 'maintenance')->count(),
            ],
            'occupancy_details' => $tables->map(function ($table) use ($request) {
                $days = $request->filled('date_from') && $request->filled('date_to') 
                    ? now()->parse($request->input('date_from'))->diffInDays(now()->parse($request->input('date_to')))
                    : 30;
                
                return [
                    'table' => new TableResource($table),
                    'stats' => $table->getOccupancyStats($days),
                    'current_occupancy_duration' => $table->getCurrentOccupancyDuration(),
                    'is_occupied_too_long' => $table->isOccupiedTooLong(),
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $report,
            'meta' => [
                'period' => [
                    'from' => $request->input('date_from', now()->subDays(30)->toDateString()),
                    'to' => $request->input('date_to', now()->toDateString()),
                ],
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }
}