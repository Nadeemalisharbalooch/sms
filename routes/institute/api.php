<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Institute\AcademicSessionController;
use App\Http\Controllers\Institute\AcademicClassController;
use App\Http\Controllers\Institute\ClassSubjectController;
use App\Http\Controllers\Institute\AcademicSectionController;
use App\Http\Controllers\Institute\InstituteController;
use App\Http\Controllers\Institute\PermissionController;
use App\Http\Controllers\Institute\RoleController;
use App\Http\Controllers\Institute\SubjectController;
use App\Http\Controllers\Institute\SubjectTeacherController;
use App\Http\Controllers\Institute\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('institute')->group(function () {
Route::middleware('auth:sanctum')->group(function (){

        // Resources

        Route::get('users/current/permissions', [UserController::class, 'currentPermissions'])
            ->name('users.current.permissions');

        Route::apiResource('roles', RoleController::class);
        Route::apiResource('users', UserController::class);
        Route::patch('/users/{id}/restore', [UserController::class, 'restore'])
            ->name('users.restore');
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

    Route::get('institutes/classes/{academic_class}/subjects', [ClassSubjectController::class, 'index'])
        ->name('institutes.classes.subjects.index');
    Route::put('institutes/classes/{academic_class}/subjects', [ClassSubjectController::class, 'sync'])
        ->name('institutes.classes.subjects.sync');

    Route::apiResource('institutes/sections', AcademicSectionController::class)
        ->parameters(['sections' => 'academic_section']);

    Route::apiResource('institutes/subjects', SubjectController::class);
    Route::apiResource('institutes/subject-teachers', SubjectTeacherController::class);

    Route::patch('institutes/{institute}/activate', [InstituteController::class, 'activate'])
        ->name('institutes.activate');

    Route::apiResource('institutes', InstituteController::class);
});
