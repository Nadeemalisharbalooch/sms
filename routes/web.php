<?php

use App\Http\Controllers\Institute\TimetableController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\LogoutController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SuperAdminInstituteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Auth Routes (Inertia)
Route::get('login', [LoginController::class, 'show'])->name('login');
Route::post('login', [LoginController::class, 'store'])->name('login.store');
Route::post('logout', [LogoutController::class, 'store'])->name('logout');

// Dashboard (Inertia)
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');
Route::get('settings', [SettingsController::class, 'index'])->name('settings')->middleware('auth');
Route::resource('institutes', SuperAdminInstituteController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->middleware('auth');

// Website URL for opening or downloading a class timetable PDF.
Route::get('institutes/timetable/export/classes', [TimetableController::class, 'export'])
    ->name('institutes.timetable.export.classes');
