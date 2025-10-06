<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $query = Category::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'sort_order');
        $sortDirection = $request->input('sort_direction', 'asc');

        if (in_array($sortBy, ['name', 'sort_order', 'created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('sort_order', 'asc');
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::create([
            'store_id' => auth()->user()->store_id,
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')),
            'description' => $request->input('description'),
            'image' => $request->input('image'),
            'is_active' => $request->input('is_active', true),
            'sort_order' => $request->input('sort_order', 0)
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Category created successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return response()->json([
            'success' => true,
            'data' => $category,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')),
            'description' => $request->input('description'),
            'image' => $request->input('image'),
            'is_active' => $request->input('is_active', $category->is_active),
            'sort_order' => $request->input('sort_order', $category->sort_order)
        ]);

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
            'message' => 'Category updated successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        // Check if category has products
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CATEGORY_HAS_PRODUCTS',
                    'message' => 'Cannot delete category that has products. Please reassign or delete products first.',
                    'details' => [
                        'products_count' => $category->products()->count()
                    ]
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get categories for dropdown/select options.
     */
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::active()
            ->ordered()
            ->select('id', 'name', 'sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }
}
