<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResendOtpController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyOtpController;
use Illuminate\Support\Facades\Route;

Route::post('login', LoginController::class)->name('login')->middleware('throttle:5,1');
Route::post('register', RegisterController::class)->name('register')->middleware('throttle:5,1');
Route::post('verify-otp', VerifyOtpController::class)->name('verify-otp')->middleware('throttle:5,1');
Route::post('resend-otp', ResendOtpController::class)->name('resend-otp')->middleware('throttle:3,1');
Route::post('forgot-password', ForgotPasswordController::class)->name('forgot-password')->middleware('throttle:5,1');
Route::post('reset-password', ResetPasswordController::class)->name('reset-password')->middleware('throttle:5,1');
Route::post('logout', LogoutController::class)->name('logout')->middleware('auth:sanctum');
