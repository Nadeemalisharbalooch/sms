<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Institute\InstituteController;
use Illuminate\Support\Facades\Route;

Route::post('login',LoginController::class)->name('login');
Route::post('register',RegisterController::class)->name('register');
Route::post('logout',LogoutController::class)->name('logout')->middleware('auth');
Route::resource('institutes',InstituteController::class);
