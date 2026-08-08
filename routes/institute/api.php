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
use App\Http\Controllers\Institute\SectionTeacherController;
use App\Http\Controllers\Institute\SubjectAllocationController;
use App\Http\Controllers\Institute\RoomTeacherController;
use App\Http\Controllers\Institute\UserController;
use Illuminate\Support\Facades\Route;

$instituteResources = function () {
    Route::middleware('auth:sanctum')->group(function () {

        // Resources

        Route::get('users/current/permissions', [UserController::class, 'currentPermissions'])
            ->name('users.current.permissions');

        Route::apiResource('roles', RoleController::class);
        Route::apiResource('users', UserController::class);
        Route::patch('/users/{id}/restore', [UserController::class, 'restore'])
            ->name('users.restore');
        Route::apiResource('permissions', PermissionController::class);
        Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDestroy'])
            ->name('users.forceDestroy');
    });
};

Route::prefix('institutes')->group($instituteResources);
Route::prefix('institute')->group($instituteResources);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('institutes/academic-sessions', AcademicSessionController::class)
        ->parameters(['academic-sessions' => 'academic_session']);

    Route::apiResource('institutes/classes', AcademicClassController::class)
        ->parameters(['classes' => 'academic_class']);

    Route::get('institutes/classes/{academic_class}/subjects', [ClassSubjectController::class, 'index'])
        ->name('institutes.classes.subjects.index');
    Route::get('institutes/classes/{academic_class}/subjects/unassigned', [ClassSubjectController::class, 'unassigned'])
        ->name('institutes.classes.subjects.unassigned');
    Route::post('institutes/classes/{academic_class}/subjects', [ClassSubjectController::class, 'sync'])
        ->name('institutes.classes.subjects.sync');
    Route::delete('institutes/classes/{academic_class}/subjects/{subject}', [ClassSubjectController::class, 'destroy'])
        ->name('institutes.classes.subjects.destroy');

    Route::apiResource('institutes/sections', AcademicSectionController::class)
        ->parameters(['sections' => 'academic_section']);

    Route::get('institutes/sections/{academic_section}/classes', [AcademicSectionController::class, 'classes'])
        ->name('institutes.sections.classes');

    Route::apiResource('institutes/subjects', SubjectController::class);
    Route::apiResource('institutes/section-teachers', SectionTeacherController::class);

    // Module 2: Subject Teacher Allocations (also available at /subject-teachers)
    Route::get('institutes/subject-teachers', [SubjectTeacherController::class, 'index'])
        ->name('institutes.subject-teachers.index');
    Route::post('institutes/subject-teachers', [SubjectTeacherController::class, 'store'])
        ->name('institutes.subject-teachers.store');
    Route::get('institutes/subject-teachers/{subject_allocation}', [SubjectTeacherController::class, 'show'])
        ->name('institutes.subject-teachers.show');
    Route::delete('institutes/subject-teachers/{subject_allocation}', [SubjectTeacherController::class, 'destroy'])
        ->name('institutes.subject-teachers.destroy');

    // Module 2: Subject Teacher Allocations
    Route::get('institutes/allocations/subject-teachers', [SubjectTeacherController::class, 'index'])
        ->name('institutes.allocations.subject-teachers.index');
    Route::post('institutes/allocations/subject-teachers', [SubjectTeacherController::class, 'store'])
        ->name('institutes.allocations.subject-teachers.store');
    Route::get('institutes/allocations/subject-teachers/{subject_allocation}', [SubjectTeacherController::class, 'show'])
        ->name('institutes.allocations.subject-teachers.show');
    Route::delete('institutes/allocations/subject-teachers/{subject_allocation}', [SubjectTeacherController::class, 'destroy'])
        ->name('institutes.allocations.subject-teachers.destroy');

    // Module 3: Room Teacher (Homeroom) Allocations
    Route::get('institutes/allocations/room-teachers', [RoomTeacherController::class, 'index'])
        ->name('institutes.allocations.room-teachers.index');
    Route::post('institutes/allocations/room-teachers', [RoomTeacherController::class, 'store'])
        ->name('institutes.allocations.room-teachers.store');
    Route::get('institutes/allocations/room-teachers/{room_teacher}', [RoomTeacherController::class, 'show'])
        ->name('institutes.allocations.room-teachers.show');
    Route::delete('institutes/allocations/room-teachers/{room_teacher}', [RoomTeacherController::class, 'destroy'])
        ->name('institutes.allocations.room-teachers.destroy');

    Route::patch('institutes/{institute}/activate', [InstituteController::class, 'activate'])
        ->name('institutes.activate');

    Route::apiResource('institutes', InstituteController::class);
});