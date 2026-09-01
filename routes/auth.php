<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResendOtpController;
use App\Http\Controllers\Auth\VerifyOtpController;
use Illuminate\Support\Facades\Route;

Route::post('login', LoginController::class)->name('login');
Route::post('register', RegisterController::class)->name('register');
Route::post('verify-otp', VerifyOtpController::class)->name('verify-otp');
Route::post('resend-otp', ResendOtpController::class)->name('resend-otp');
Route::post('logout', LogoutController::class)->name('logout')->middleware('auth:sanctum');
