<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Institute\AcademicSessionController;
use App\Http\Controllers\Institute\AcademicClassController;
use App\Http\Controllers\Institute\AcademicSectionController;
use App\Http\Controllers\Institute\InstituteController;
use App\Http\Controllers\Institute\PermissionController;
use App\Http\Controllers\Institute\RoleController;
use App\Http\Controllers\Institute\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('institute')->group(function () {
Route::middleware('auth:sanctum')->group(function (){

        // Resources

        Route::apiResource('roles', RoleController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('permissions',PermissionController::class);
        Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDestroy'])
    ->name('users.forceDestroy');
});
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('institutes/academic-sessions', AcademicSessionController::class)
        ->parameters(['academic-sessions' => 'academic_session']);

    Route::apiResource('institutes/classes', AcademicClassController::class)
        ->parameters(['classes' => 'academic_class']);

    Route::apiResource('institutes/sections', AcademicSectionController::class)
        ->parameters(['sections' => 'academic_section']);

    Route::apiResource('institutes', InstituteController::class);
});
