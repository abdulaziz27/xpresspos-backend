<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()->active()->ordered()->get();

        return view('landing.pages.home', [
            'plans' => $plans,
        ]);
    }

    public function pricing(): View
    {
        $plans = Plan::query()->active()->ordered()->get();

        return view('landing.pages.pricing', [
            'plans' => $plans,
        ]);
    }
}
