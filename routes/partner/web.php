<?php

use App\Http\Controllers\Partner\AdminPartnerController;
use Illuminate\Support\Facades\Route;

Route::prefix('operations-center-6975')->middleware(['auth', 'admin'])->group(function () {
    Route::get('partners', [AdminPartnerController::class, 'index'])->name('partners.admin.index');
    Route::patch('partners/{partner}', [AdminPartnerController::class, 'update'])->whereNumber('partner')->name('partners.admin.update');
    Route::patch('partners/withdrawals/{withdrawal}', [AdminPartnerController::class, 'processWithdrawal'])->whereNumber('withdrawal')->name('partners.admin.withdrawals.process');
});
