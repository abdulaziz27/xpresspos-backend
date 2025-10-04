<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Handle Midtrans notification webhook
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            Log::info('Midtrans webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ]);

            $notification = $request->all();

            // Validate required fields
            if (!isset($notification['order_id']) || !isset($notification['transaction_status'])) {
                Log::warning('Invalid Midtrans notification: missing required fields', [
                    'notification' => $notification
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid notification format'
                ], 400);
            }

            // Process the notification
            $success = $this->paymentService->handleNotification($notification);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Notification processed successfully'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to process notification'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Midtrans webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }
}