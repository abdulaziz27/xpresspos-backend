<?php

use App\Http\Controllers\Owner\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('owner.dashboard');
