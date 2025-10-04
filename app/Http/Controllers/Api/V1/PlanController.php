<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::query()->active()->ordered()->get();

        return PlanResource::collection($plans)
            ->additional([
                'success' => true,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'count' => $plans->count(),
                ],
            ])
            ->response();
    }
}
