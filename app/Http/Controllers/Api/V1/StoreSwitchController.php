<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StoreSwitchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StoreSwitchController extends Controller
{
    public function __construct(
        private StoreSwitchingService $storeSwitchingService
    ) {
        $this->middleware(['auth:sanctum', 'role:admin_sistem']);
    }

    /**
     * Get available stores for switching.
     */
    public function index(): JsonResponse
    {
        try {
            $stores = $this->storeSwitchingService->getAvailableStores(auth()->user());
            $currentContext = $this->storeSwitchingService->getCurrentStoreInfo(auth()->user());

            return response()->json([
                'success' => true,
                'data' => [
                    'available_stores' => $stores,
                    'current_context' => $currentContext,
                    'is_in_store_context' => $this->storeSwitchingService->isInStoreContext(auth()->user()),
                ],
                'message' => 'Available stores retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_SWITCH_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Switch to a specific store context.
     */
    public function switch(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'store_id' => 'required|string|exists:stores,id',
            ]);

            $success = $this->storeSwitchingService->switchStore(
                auth()->user(),
                $request->input('store_id')
            );

            if ($success) {
                $storeInfo = $this->storeSwitchingService->getCurrentStoreInfo(auth()->user());

                return response()->json([
                    'success' => true,
                    'data' => [
                        'store_context' => $storeInfo,
                        'message' => "Switched to store: {$storeInfo['name']}",
                    ],
                    'message' => 'Store context switched successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_SWITCH_FAILED',
                    'message' => 'Failed to switch store context',
                ],
            ], 500);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors(),
                ],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_SWITCH_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 403);
        }
    }

    /**
     * Clear store context and return to global admin view.
     */
    public function clear(): JsonResponse
    {
        try {
            $success = $this->storeSwitchingService->clearStoreContext(auth()->user());

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => 'Returned to global admin view',
                    ],
                    'message' => 'Store context cleared successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONTEXT_CLEAR_FAILED',
                    'message' => 'Failed to clear store context',
                ],
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONTEXT_CLEAR_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 403);
        }
    }

    /**
     * Get current store context information.
     */
    public function current(): JsonResponse
    {
        try {
            $storeInfo = $this->storeSwitchingService->getCurrentStoreInfo(auth()->user());
            $isInContext = $this->storeSwitchingService->isInStoreContext(auth()->user());

            return response()->json([
                'success' => true,
                'data' => [
                    'current_store' => $storeInfo,
                    'is_in_store_context' => $isInContext,
                    'can_switch_stores' => auth()->user()->hasRole('admin_sistem'),
                ],
                'message' => 'Current store context retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONTEXT_INFO_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}