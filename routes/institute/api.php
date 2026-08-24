<?php

use App\Http\Controllers\Institute\AcademicClassController;
use App\Http\Controllers\Institute\AcademicSectionController;
use App\Http\Controllers\Institute\AcademicSessionController;
use App\Http\Controllers\Institute\AttendanceController;
use App\Http\Controllers\Institute\ClassSubjectController;
use App\Http\Controllers\Institute\FeeController;
use App\Http\Controllers\Institute\InstituteController;
use App\Http\Controllers\Institute\PermissionController;
use App\Http\Controllers\Institute\RoleController;
use App\Http\Controllers\Institute\RoomTeacherController;
use App\Http\Controllers\Institute\SectionTeacherController;
use App\Http\Controllers\Institute\StudentController;
use App\Http\Controllers\Institute\SubjectController;
use App\Http\Controllers\Institute\SubjectTeacherController;
use App\Http\Controllers\Institute\TimetableController;
use App\Http\Controllers\Institute\UserController;
use Illuminate\Support\Facades\Route;

$instituteResources = function () {
    Route::middleware('auth:sanctum')->group(function () {

        // Resources

        Route::get('users/current/permissions', [UserController::class, 'currentPermissions'])
            ->name('users.current.permissions');

        Route::get('users/teachers', [UserController::class, 'teachers'])
            ->name('users.teachers');

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
    Route::patch('institutes/academic-sessions/{academic_session}/activate', [AcademicSessionController::class, 'activate'])
        ->name('institutes.academic-sessions.activate');

    Route::apiResource('institutes/academic-sessions', AcademicSessionController::class)
        ->parameters(['academic-sessions' => 'academic_session']);

    Route::apiResource('institutes/classes', AcademicClassController::class)
        ->parameters(['classes' => 'academic_class']);

    Route::get('institutes/assigned-subjects', [ClassSubjectController::class, 'assignedSubjects'])
        ->name('institutes.assigned-subjects.index');
    Route::get('institutes/class-subjects', [ClassSubjectController::class, 'assignedSubjects'])
        ->name('institutes.class-subjects.index');
    Route::get('institutes/classes/{academic_class}/subjects', [ClassSubjectController::class, 'index'])
        ->name('institutes.classes.subjects.index');
    Route::post('institutes/classes/{academic_class}/subjects', [ClassSubjectController::class, 'sync'])
        ->name('institutes.classes.subjects.sync');
    Route::delete('institutes/classes/{academic_class}/subjects/{subject}', [ClassSubjectController::class, 'getrecords'])
        ->name('institutes.classes.subjects.destroy');

    Route::apiResource('institutes/sections', AcademicSectionController::class)
        ->parameters(['sections' => 'academic_section']);

    Route::get('institutes/teacher/attendance-tasks', [AttendanceController::class, 'tasks'])
        ->name('institutes.teacher.attendance-tasks');
    Route::get('institutes/attendance/roster', [AttendanceController::class, 'roster'])
        ->name('institutes.attendance.roster');
    Route::get('institutes/attendance', [AttendanceController::class, 'index'])
        ->name('institutes.attendance.index');
    Route::get('institutes/attendance/records', [AttendanceController::class, 'index'])
        ->name('institutes.attendance.records');
    Route::post('institutes/attendance', [AttendanceController::class, 'store'])
        ->name('institutes.attendance.store');

    Route::patch('institutes/students/{student}/enrollment', [StudentController::class, 'updateEnrollment'])
        ->name('institutes.students.enrollment.update');
    Route::post('institutes/students/{student}/promote', [StudentController::class, 'promote'])
        ->name('institutes.students.promote');
    Route::post('institutes/students/promote-class', [StudentController::class, 'promoteClass'])
        ->name('institutes.students.promote-class');
    Route::apiResource('institutes/students', StudentController::class);

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

    // =====================================================================
    // Module 6: Smart Fees Management
    // =====================================================================

    // API 1: Fee Categories (master data)
    Route::get('institutes/fee-categories', [FeeController::class, 'indexCategories'])
        ->name('institutes.fee-categories.index');
    Route::post('institutes/fee-categories', [FeeController::class, 'storeCategory'])
        ->name('institutes.fee-categories.store');
    Route::patch('institutes/fee-categories/{categoryId}', [FeeController::class, 'updateCategory'])
        ->name('institutes.fee-categories.update');
    Route::delete('institutes/fee-categories/{categoryId}', [FeeController::class, 'destroyCategory'])
        ->name('institutes.fee-categories.destroy');

    // API 2: Class Fee Structures
    Route::get('institutes/fee-structures', [FeeController::class, 'indexFeeStructures'])
        ->name('institutes.fee-structures.index');
    Route::post('institutes/fee-structures', [FeeController::class, 'storeFeeStructure'])
        ->name('institutes.fee-structures.store');
    Route::delete('institutes/fee-structures/{structureId}', [FeeController::class, 'destroyFeeStructure'])
        ->name('institutes.fee-structures.destroy');

    // API 3: Student-Specific Fee Assignments
    Route::get('institutes/fees/student-assignments', [FeeController::class, 'indexStudentAssignments'])
        ->name('institutes.fees.student-assignments.index');
    Route::post('institutes/fees/student-assignments', [FeeController::class, 'storeStudentAssignment'])
        ->name('institutes.fees.student-assignments.store');
    Route::delete('institutes/fees/student-assignments/{assignmentId}', [FeeController::class, 'destroyStudentAssignment'])
        ->name('institutes.fees.student-assignments.destroy');

    // API 4: Smart Bulk Voucher Generation (The Engine)
    Route::post('institutes/fees/generate-vouchers', [FeeController::class, 'generateVouchers'])
        ->name('institutes.fees.generate-vouchers');
    Route::delete('institutes/fees/generate-vouchers', [FeeController::class, 'destroyVouchers'])
        ->name('institutes.fees.generate-vouchers.destroy');
    Route::delete('institutes/fees/vouchers', [FeeController::class, 'destroyVouchers'])
        ->name('institutes.fees.vouchers.destroyBulk');
    Route::delete('institutes/fees/vouchers/{voucherId}', [FeeController::class, 'destroyVoucher'])
        ->name('institutes.fees.vouchers.destroy');

    // API 5: Fetch Student Ledger (Cashier Search)
    Route::get('institutes/fees/ledger', [FeeController::class, 'ledger'])
        ->name('institutes.fees.ledger');

    // API 5A: Fetch one student's voucher ledger and summary.
    Route::get('institutes/fees/student-ledger', [FeeController::class, 'studentLedger'])
        ->name('institutes.fees.student-ledger');

    // API 5B: Fetch student's fee vouchers list
    Route::get('institutes/fees/student-vouchers', [FeeController::class, 'studentVouchers'])
        ->name('institutes.fees.student-vouchers');
    Route::get('institutes/fees/vouchers', [FeeController::class, 'studentVouchers'])
        ->name('institutes.fees.vouchers.index');

    // API 6: Collect Payment
    Route::post('institutes/fees/collect', [FeeController::class, 'collect'])
        ->name('institutes.fees.collect');

    // =====================================================================
    // Module 7: Timetable Generator & Scheduling
    // =====================================================================
    // Step 1: Shifts & Time Slots Setup (Admin Period Duration + Standard Days & Friday Timings)
    Route::post('institutes/timetable/shifts', [TimetableController::class, 'setupShifts'])
        ->name('institutes.timetable.shifts.setup');
    Route::post('institutes/timetable/setup-slots', [TimetableController::class, 'setupShifts'])
        ->name('institutes.timetable.slots.setup');

    // Step 2: Subject Weightage (Curriculum per Class/Grade)
    Route::get('institutes/timetable/curriculum', [TimetableController::class, 'getCurriculum'])
        ->name('institutes.timetable.curriculum.get');
    Route::post('institutes/timetable/curriculum', [TimetableController::class, 'saveCurriculum'])
        ->name('institutes.timetable.curriculum.save');

    // Step 3 / Unified All-In-One Wizard: Step 1 (Timing) + Step 2 (Curriculum) + Step 3 (Generate)
    Route::post('institutes/timetable/wizard-generate', [TimetableController::class, 'setupAndGenerate'])
        ->name('institutes.timetable.wizard.generate');

    // Individual Slot CRUD
    Route::get('institutes/timetable/slots', [TimetableController::class, 'indexSlots'])
        ->name('institutes.timetable.slots.index');
    Route::post('institutes/timetable/slots', [TimetableController::class, 'storeSlot'])
        ->name('institutes.timetable.slots.store');
    Route::put('institutes/timetable/slots/{timeSlot}', [TimetableController::class, 'updateSlot'])
        ->name('institutes.timetable.slots.update');
    Route::delete('institutes/timetable/slots/{timeSlot}', [TimetableController::class, 'destroySlot'])
        ->name('institutes.timetable.slots.destroy');
    Route::post('institutes/timetable/slots/preset', [TimetableController::class, 'seedPresetSlots'])
        ->name('institutes.timetable.slots.preset');

    // Core Generator
    Route::post('institutes/timetable/generate', [TimetableController::class, 'generate'])
        ->name('institutes.timetable.generate');

    Route::get('institutes/timetable/class', [TimetableController::class, 'classSchedule'])
        ->name('institutes.timetable.class');
    Route::get('institutes/timetable/teacher', [TimetableController::class, 'teacherSchedule'])
        ->name('institutes.timetable.teacher');
    Route::get('institutes/timetable/master', [TimetableController::class, 'masterSchedule'])
        ->name('institutes.timetable.master');

    Route::post('institutes/timetable/swap', [TimetableController::class, 'swap'])
        ->name('institutes.timetable.swap');

    Route::get('institutes/timetable/export', [TimetableController::class, 'export'])
        ->name('institutes.timetable.export');

    Route::apiResource('institutes', InstituteController::class);
});
