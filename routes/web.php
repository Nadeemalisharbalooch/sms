<?php

use App\Http\Controllers\Institute\TimetableController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\LogoutController;
use App\Http\Controllers\Web\PlanController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SubscriptionInvoiceController;
use App\Http\Controllers\Web\SuperAdminInstituteController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect()->route('login.page');
});

// Auth Routes (Inertia)
Route::get('login', [LoginController::class, 'show'])->name('login.page');
Route::post('login', [LoginController::class, 'store'])->name('login.store');
Route::post('logout', [LogoutController::class, 'store'])->name('logout.web');

// Dashboard (Inertia)
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');
Route::get('settings', [SettingsController::class, 'index'])->name('settings')->middleware('auth');
Route::resource('institute', SuperAdminInstituteController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->middleware('auth');
Route::resource('plans', PlanController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->middleware('auth');
Route::get('subscription-invoices', [SubscriptionInvoiceController::class, 'index'])
    ->name('subscription-invoices.index')
    ->middleware('auth');
Route::put('subscription-invoices/{invoice}/verify', [SuperAdminInstituteController::class, 'verifyInvoice'])
    ->name('subscription-invoices.verify')
    ->middleware('auth');

// Fallback for "public/storage/..." URLs. When the web server's document
// root is the Laravel "public/" directory, a static file does not exist at
// that path, so Laravel serves the file straight from the public disk.
Route::get('public/storage/{path}', function (string $path) {
    abort_if(str_contains($path, '..'), 404);

    return Storage::disk('public')->response($path);
})->where('path', '.*');

// Website URL for opening or downloading a class timetable PDF.
Route::get('institutes/timetable/export/classes', [TimetableController::class, 'export'])
    ->name('institutes.timetable.export.classes');
