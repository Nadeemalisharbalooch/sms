<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Institute\InstituteController;
use App\Http\Controllers\Institute\RoleController;
use App\Http\Controllers\Institute\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('institute')->group(function () {
Route::middleware('auth:sanctum')->group(function (){

        // Resources
        Route::apiResource('institutes', InstituteController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('users', UserController::class);
});
});

