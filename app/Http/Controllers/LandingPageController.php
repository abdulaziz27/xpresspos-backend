<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LandingPageController
{
    /**
     * Display the public landing page.
     */
    public function __invoke(): View
    {
        return view('landing', [
            'title' => 'POS Xpress - Modern POS Platform',
        ]);
    }
}
