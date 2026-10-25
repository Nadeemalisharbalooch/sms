<?php

use App\Http\Controllers\Partner\AdminPartnerController;
use App\Http\Controllers\Partner\PartnerController;
use Illuminate\Support\Facades\Route;

// Partner APIs are versioned and intentionally isolated from institute APIs.
Route::prefix('partners')->group(function () {
    Route::get('track', [PartnerController::class, 'track'])->middleware('throttle:60,1')->name('partners.track');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('setup', [PartnerController::class, 'setupStatus'])->name('partners.setup.status');
        Route::post('setup', [PartnerController::class, 'setup'])->name('partners.setup');
        Route::put('setup', [PartnerController::class, 'updateSetup'])->name('partners.setup.update');
        Route::get('dashboard', [PartnerController::class, 'dashboard'])->name('partners.dashboard');
        Route::put('payout-settings', [PartnerController::class, 'payoutSettings'])->name('partners.payout-settings');
        Route::get('withdrawals', [PartnerController::class, 'withdrawals'])->name('partners.withdrawals.index');
        Route::post('withdrawals', [PartnerController::class, 'withdrawals'])->name('partners.withdrawals.store');
        Route::get('referrals', [PartnerController::class, 'referrals'])->name('partners.referrals');
    });
});
Route::prefix('v1/admin/partners')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/', [AdminPartnerController::class, 'index'])->name('api.admin.partners.index');
    Route::patch('{partner}', [AdminPartnerController::class, 'update'])->whereNumber('partner')->name('api.admin.partners.update');
    Route::patch('withdrawals/{withdrawal}', [AdminPartnerController::class, 'processWithdrawal'])->whereNumber('withdrawal')->name('api.admin.partners.withdrawals.process');
});
