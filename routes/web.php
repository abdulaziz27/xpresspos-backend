<?php

use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

// Root route
Route::get('/', LandingPageController::class)->name('landing');

Route::view('/login', 'auth.login')->name('login');
Route::view('/company', 'company')->name('company');
