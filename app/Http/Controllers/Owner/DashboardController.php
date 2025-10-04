<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\OwnerDashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly OwnerDashboardService $dashboardService)
    {
    }

    public function index(): View
    {
        $summary = $this->dashboardService->summaryFor(Auth::user());

        return view('owner.dashboard', [
            'summary' => $summary,
            'trendData' => $summary['trends'] ?? ['revenue' => collect(), 'orders' => collect()],
        ]);
    }
}
