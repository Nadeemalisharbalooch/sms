<?php

use App\Http\Controllers\Institute\AcademicClassController;
use App\Http\Controllers\Institute\AcademicSectionController;
use App\Http\Controllers\Institute\AcademicSessionController;
use App\Http\Controllers\Institute\AttendanceController;
use App\Http\Controllers\Institute\AttendanceReportController;
use App\Http\Controllers\Institute\ClassSubjectController;
use App\Http\Controllers\Institute\DashboardController;
use App\Http\Controllers\Institute\FeeController;
use App\Http\Controllers\Institute\FeeReportController;
use App\Http\Controllers\Institute\EmailChangeController;
use App\Http\Controllers\Institute\InstituteController;
use App\Http\Controllers\Institute\NotificationController;
use App\Http\Controllers\Institute\PermissionController;
use App\Http\Controllers\Institute\RoleController;
use App\Http\Controllers\Institute\RoomTeacherController;
use App\Http\Controllers\Institute\SectionTeacherController;
use App\Http\Controllers\Institute\StudentController;
use App\Http\Controllers\Institute\SubjectController;
use App\Http\Controllers\Institute\SubjectTeacherController;
use App\Http\Controllers\Institute\SubscriptionController;
use App\Http\Controllers\Institute\TeacherFeeStatusController;
use App\Http\Controllers\Institute\TimetableController;
use App\Http\Controllers\Institute\UserController;

// TeacherDashboardController has been folded into DashboardController.
use App\Http\Controllers\Institute\DashboardController as TeacherDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Permission policy
|--------------------------------------------------------------------------
| Every route is gated behind config/app-permissions.php permissions of the
| form "{module}.{action}", enforced by the institute-scoped 'permission'
| middleware (App\Http\Middleware\EnsureHasPermission). Self-service /
| "own data" endpoints are intentionally left ungated.
|--------------------------------------------------------------------------
*/

$instituteResources = function () {
    Route::middleware(['auth:sanctum', 'email.verified', 'active.institute.subscription'])->group(function () {

        // =====================================================================
        // Self-service endpoints (no module permission required)
        // =====================================================================

        Route::get('users/current', [UserController::class, 'showCurrent'])
            ->name('users.current.show');

        Route::post('users/current/email/request-otp', [EmailChangeController::class, 'requestOtp'])
            ->middleware('throttle:3,1')->name('users.current.email.request-otp');
        Route::post('users/current/email/verify-otp', [EmailChangeController::class, 'verifyOtp'])
            ->middleware('throttle:5,1')->name('users.current.email.verify-otp');

        Route::get('users/current/permissions', [UserController::class, 'currentPermissions'])
            ->name('users.current.permissions');

        Route::get('users/teacher/dashboard-summary', [TeacherDashboardController::class, 'summary'])
            ->name('users.teacher.dashboard-summary');

        // =====================================================================
        // Users module (users.view / .create / .update / .delete)
        // =====================================================================

        Route::middleware('permission:users.view')->group(function () {
            Route::get('users/teachers', [UserController::class, 'teachers'])
                ->name('users.teachers');
            Route::get('users/teachers/{teacherId}', [UserController::class, 'teacherView'])
                ->whereNumber('teacherId')->name('users.teachers.show');

            Route::apiResource('users', UserController::class)->only(['index', 'show']);
        });

        Route::middleware('permission:users.create')->group(function () {
            Route::post('users/teachers', [UserController::class, 'storeTeacher'])
                ->name('users.teachers.store');

            Route::apiResource('users', UserController::class)->only(['store']);
        });

        Route::middleware('permission:users.update')->group(function () {
            Route::apiResource('users', UserController::class)->only(['update']);

            Route::patch('/users/{id}/restore', [UserController::class, 'restore'])
                ->name('users.restore');
        });

        Route::middleware('permission:users.delete')->group(function () {
            Route::apiResource('users', UserController::class)->only(['destroy']);

            Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDestroy'])
                ->name('users.forceDestroy');
        });

        // =====================================================================
        // Roles module (roles.view / .create / .update / .delete)
        // =====================================================================

        Route::apiResource('roles', RoleController::class)
            ->only(['index', 'show'])->middleware('permission:roles.view');

        Route::middleware('permission:roles.create')->group(function () {
            Route::apiResource('roles', RoleController::class)->only(['store']);
        });

        Route::middleware('permission:roles.update')->group(function () {
            Route::apiResource('roles', RoleController::class)->only(['update']);
        });

        Route::middleware('permission:roles.delete')->group(function () {
            Route::apiResource('roles', RoleController::class)->only(['destroy']);
        });

        // =====================================================================
        // Permissions module (permissions.view / .create / .update / .delete)
        // =====================================================================

        Route::apiResource('permissions', PermissionController::class)
            ->only(['index', 'show'])->middleware('permission:permissions.view');

        Route::middleware('permission:permissions.create')->group(function () {
            Route::apiResource('permissions', PermissionController::class)->only(['store']);
        });

        Route::middleware('permission:permissions.update')->group(function () {
            Route::apiResource('permissions', PermissionController::class)->only(['update']);
        });

        Route::middleware('permission:permissions.delete')->group(function () {
            Route::apiResource('permissions', PermissionController::class)->only(['destroy']);
        });
    });
};

Route::prefix('institutes')->group($instituteResources);
Route::prefix('institute')->group($instituteResources);

// Dashboard notification tray. Auth only (no subscription requirement) so a
// blocked/expired user can still read their alerts.
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('institutes/notifications', [NotificationController::class, 'index'])
        ->name('institutes.notifications.index');
    Route::get('institutes/notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('institutes.notifications.unread-count');
    Route::patch('institutes/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('institutes.notifications.read-all');
    Route::patch('institutes/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('institutes.notifications.read');
    Route::delete('institutes/notifications/{id}', [NotificationController::class, 'destroy'])
        ->name('institutes.notifications.destroy');
});

Route::middleware(['auth:sanctum', 'email.verified', 'active.institute.subscription'])->group(function () {

    // =====================================================================
    // Academic sessions (academic-sessions.view / .manage / .activate)
    // =====================================================================

    Route::middleware('permission:academic-sessions.activate')->group(function () {
        Route::patch('institutes/academic-sessions/{academic_session}/activate', [AcademicSessionController::class, 'activate'])
            ->name('institutes.academic-sessions.activate');
    });

    Route::apiResource('institutes/academic-sessions', AcademicSessionController::class)
        ->only(['index', 'show'])
        ->middleware('permission:academic-sessions.view')
        ->parameters(['academic-sessions' => 'academic_session']);

    Route::middleware('permission:academic-sessions.manage')->group(function () {
        Route::apiResource('institutes/academic-sessions', AcademicSessionController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['academic-sessions' => 'academic_session']);
    });

    // =====================================================================
    // Classes (classes.view / .create / .update / .delete)
    // =====================================================================

    Route::apiResource('institutes/classes', AcademicClassController::class)
        ->only(['index', 'show'])
        ->middleware('permission:classes.view')
        ->parameters(['classes' => 'academic_class']);

    Route::middleware('permission:classes.create')->group(function () {
        Route::apiResource('institutes/classes', AcademicClassController::class)
            ->only(['store'])->parameters(['classes' => 'academic_class']);
    });

    Route::middleware('permission:classes.update')->group(function () {
        Route::apiResource('institutes/classes', AcademicClassController::class)
            ->only(['update'])->parameters(['classes' => 'academic_class']);
    });

    Route::middleware('permission:classes.delete')->group(function () {
        Route::apiResource('institutes/classes', AcademicClassController::class)
            ->only(['destroy'])->parameters(['classes' => 'academic_class']);
    });

    // =====================================================================
    // Class subjects (teacher-assignments.view / .manage)
    // =====================================================================

    Route::get('institutes/assigned-subjects', [ClassSubjectController::class, 'assignedSubjects'])
        ->name('institutes.assigned-subjects.index');
    Route::get('institutes/class-subjects', [ClassSubjectController::class, 'assignedSubjects'])
        ->name('institutes.class-subjects.index');

    Route::middleware('permission:subjects.view')->group(function () {
        Route::get('institutes/classes/{academic_class}/subjects', [ClassSubjectController::class, 'index'])
            ->name('institutes.classes.subjects.index');
    });

    Route::middleware('permission:teacher-assignments.manage')->group(function () {
        Route::post('institutes/classes/{academic_class}/subjects', [ClassSubjectController::class, 'sync'])
            ->name('institutes.classes.subjects.sync');
        Route::delete('institutes/classes/{academic_class}/subjects/{subject}', [ClassSubjectController::class, 'getrecords'])
            ->name('institutes.classes.subjects.destroy');
    });

    // =====================================================================
    // Sections (sections.view / .create / .update / .delete)
    // =====================================================================

    Route::apiResource('institutes/sections', AcademicSectionController::class)
        ->only(['index', 'show'])
        ->middleware('permission:sections.view')
        ->parameters(['sections' => 'academic_section']);

    Route::middleware('permission:sections.create')->group(function () {
        Route::apiResource('institutes/sections', AcademicSectionController::class)
            ->only(['store'])->parameters(['sections' => 'academic_section']);
    });

    Route::middleware('permission:sections.update')->group(function () {
        Route::apiResource('institutes/sections', AcademicSectionController::class)
            ->only(['update'])->parameters(['sections' => 'academic_section']);
    });

    Route::middleware('permission:sections.delete')->group(function () {
        Route::apiResource('institutes/sections', AcademicSectionController::class)
            ->only(['destroy'])->parameters(['sections' => 'academic_section']);
    });

    Route::middleware('permission:sections.view')->group(function () {
        Route::get('institutes/sections/{academic_section}/subjects', [ClassSubjectController::class, 'sectionSubjects'])
            ->name('institutes.sections.subjects.index');
        Route::get('institutes/sections/{academic_section}/classes', [AcademicSectionController::class, 'classes'])
            ->name('institutes.sections.classes');
    });

    Route::middleware('permission:teacher-assignments.manage')->group(function () {
        Route::post('institutes/sections/{academic_section}/subjects', [ClassSubjectController::class, 'syncSectionSubjects'])
            ->name('institutes.sections.subjects.sync');
    });

    // =====================================================================
    // Attendance (attendance.view / .mark / .reports)
    // =====================================================================

    Route::get('institutes/teacher/attendance-tasks', [AttendanceController::class, 'tasks'])
        ->name('institutes.teacher.attendance-tasks');

    Route::middleware('permission:attendance.view')->group(function () {
        Route::get('institutes/attendance/roster', [AttendanceController::class, 'roster'])
            ->name('institutes.attendance.roster');
        Route::get('institutes/attendance', [AttendanceController::class, 'index'])
            ->name('institutes.attendance.index');
        Route::get('institutes/attendance/records', [AttendanceController::class, 'records'])
            ->name('institutes.attendance.records');
    });

    Route::middleware('permission:attendance.reports')->group(function () {
        Route::get('institutes/attendance/overview', [AttendanceReportController::class, 'overview'])
            ->name('institutes.attendance.overview');
        Route::get('institutes/attendance/absentees', [AttendanceReportController::class, 'absentees'])
            ->name('institutes.attendance.absentees');
        Route::get('institutes/attendance/monthly-register', [AttendanceReportController::class, 'monthlyRegister'])
            ->name('institutes.attendance.monthly-register');
        Route::get('institutes/attendance/class-summary', [AttendanceReportController::class, 'classSummary'])
            ->name('institutes.attendance.class-summary');
        Route::get('institutes/attendance/student/{studentId}', [AttendanceReportController::class, 'student'])
            ->whereNumber('studentId')->name('institutes.attendance.student');
    });

    Route::middleware('permission:attendance.mark')->group(function () {
        Route::post('institutes/attendance', [AttendanceController::class, 'store'])
            ->name('institutes.attendance.store');
    });

    // =====================================================================
    // Students (students.view / .create / .update / .delete / .import / .promote)
    // =====================================================================

    Route::middleware('permission:students.import')->group(function () {
        Route::get('institutes/students/import-template', [StudentController::class, 'importTemplate'])
            ->name('institutes.students.import-template');
        Route::post('institutes/students/import', [StudentController::class, 'import'])
            ->name('institutes.students.import');
        Route::post('institutes/students/bulk-import', [StudentController::class, 'import'])
            ->name('institutes.students.bulk-import');
        Route::post('institutes/students/import-excel', [StudentController::class, 'import'])
            ->name('institutes.students.import-excel');
    });

    Route::middleware('permission:students.update')->group(function () {
        Route::patch('institutes/students/{student}/enrollment', [StudentController::class, 'updateEnrollment'])
            ->name('institutes.students.enrollment.update');
    });

    Route::middleware('permission:students.promote')->group(function () {
        Route::post('institutes/students/{student}/promote', [StudentController::class, 'promote'])
            ->name('institutes.students.promote');
        Route::post('institutes/students/promote-class', [StudentController::class, 'promoteClass'])
            ->name('institutes.students.promote-class');
    });

    Route::apiResource('institutes/students', StudentController::class)
        ->only(['index', 'show'])->middleware('permission:students.view');

    Route::middleware('permission:students.create')->group(function () {
        Route::apiResource('institutes/students', StudentController::class)->only(['store']);
    });

    Route::middleware('permission:students.update')->group(function () {
        Route::apiResource('institutes/students', StudentController::class)->only(['update']);
    });

    Route::middleware('permission:students.delete')->group(function () {
        Route::apiResource('institutes/students', StudentController::class)->only(['destroy']);
    });

    // =====================================================================
    // Subjects (subjects.view / .create / .update / .delete)
    // =====================================================================

    Route::apiResource('institutes/subjects', SubjectController::class)
        ->only(['index', 'show'])->middleware('permission:subjects.view');

    Route::middleware('permission:subjects.create')->group(function () {
        Route::apiResource('institutes/subjects', SubjectController::class)->only(['store']);
    });

    Route::middleware('permission:subjects.update')->group(function () {
        Route::apiResource('institutes/subjects', SubjectController::class)->only(['update']);
    });

    Route::middleware('permission:subjects.delete')->group(function () {
        Route::apiResource('institutes/subjects', SubjectController::class)->only(['destroy']);
    });

    // =====================================================================
    // Teacher assignments (teacher-assignments.view / .manage)
    // Merged: section-teachers, subject-teachers, room-teachers
    // =====================================================================

    Route::apiResource('institutes/section-teachers', SectionTeacherController::class)
        ->only(['index', 'show'])->middleware('permission:teacher-assignments.view');

    Route::middleware('permission:teacher-assignments.manage')->group(function () {
        Route::apiResource('institutes/section-teachers', SectionTeacherController::class)
            ->only(['store', 'update', 'destroy']);
    });

    Route::middleware('permission:teacher-assignments.view')->group(function () {
        // Module 2: Subject Teacher Allocations (also available at /subject-teachers)
        Route::get('institutes/subject-teachers', [SubjectTeacherController::class, 'index'])
            ->name('institutes.subject-teachers.index');
        Route::get('institutes/subject-teachers/{subject_allocation}', [SubjectTeacherController::class, 'show'])
            ->name('institutes.subject-teachers.show');

        Route::get('institutes/allocations/subject-teachers', [SubjectTeacherController::class, 'index'])
            ->name('institutes.allocations.subject-teachers.index');
        Route::get('institutes/allocations/subject-teachers/{subject_allocation}', [SubjectTeacherController::class, 'show'])
            ->name('institutes.allocations.subject-teachers.show');

        // Module 3: Room Teacher (Homeroom) Allocations
        Route::get('institutes/allocations/room-teachers', [RoomTeacherController::class, 'index'])
            ->name('institutes.allocations.room-teachers.index');
        Route::get('institutes/allocations/room-teachers/{room_teacher}', [RoomTeacherController::class, 'show'])
            ->name('institutes.allocations.room-teachers.show');
    });

    Route::middleware('permission:teacher-assignments.manage')->group(function () {
        Route::post('institutes/subject-teachers', [SubjectTeacherController::class, 'store'])
            ->name('institutes.subject-teachers.store');
        Route::delete('institutes/subject-teachers/{subject_allocation}', [SubjectTeacherController::class, 'destroy'])
            ->name('institutes.subject-teachers.destroy');

        Route::post('institutes/allocations/subject-teachers', [SubjectTeacherController::class, 'store'])
            ->name('institutes.allocations.subject-teachers.store');
        Route::delete('institutes/allocations/subject-teachers/{subject_allocation}', [SubjectTeacherController::class, 'destroy'])
            ->name('institutes.allocations.subject-teachers.destroy');

        Route::post('institutes/allocations/room-teachers', [RoomTeacherController::class, 'store'])
            ->name('institutes.allocations.room-teachers.store');
        Route::delete('institutes/allocations/room-teachers/{room_teacher}', [RoomTeacherController::class, 'destroy'])
            ->name('institutes.allocations.room-teachers.destroy');
    });

    Route::patch('institutes/{institute}/activate', [InstituteController::class, 'activate'])
        ->name('institutes.activate');

    Route::put('institutes/current', [InstituteController::class, 'editCurrentInstitute'])
        ->middleware('permission:institute.update')
        ->name('institutes.current.update');

    // =====================================================================
    // Module 6: Smart Fees Management
    // =====================================================================

    // API 1: Fee Categories (within fee-setup.view / .manage)
    Route::get('institutes/fee-categories', [FeeController::class, 'indexCategories'])
        ->middleware('permission:fee-setup.view')
        ->name('institutes.fee-categories.index');
    Route::post('institutes/fee-categories', [FeeController::class, 'storeCategory'])
        ->middleware('permission:fee-setup.manage')
        ->name('institutes.fee-categories.store');
    Route::patch('institutes/fee-categories/{categoryId}', [FeeController::class, 'updateCategory'])
        ->middleware('permission:fee-setup.manage')
        ->name('institutes.fee-categories.update');
    Route::delete('institutes/fee-categories/{categoryId}', [FeeController::class, 'destroyCategory'])
        ->middleware('permission:fee-setup.manage')
        ->name('institutes.fee-categories.destroy');

    // API 2: Class Fee Structures (within fee-setup.view / .manage)
    Route::get('institutes/fee-structures', [FeeController::class, 'indexFeeStructures'])
        ->middleware('permission:fee-setup.view')
        ->name('institutes.fee-structures.index');
    Route::post('institutes/fee-structures', [FeeController::class, 'storeFeeStructure'])
        ->middleware('permission:fee-setup.manage')
        ->name('institutes.fee-structures.store');
    Route::delete('institutes/fee-structures/{structureId}', [FeeController::class, 'destroyFeeStructure'])
        ->middleware('permission:fee-setup.manage')
        ->name('institutes.fee-structures.destroy');

    // API 3: Student-Specific Fee Assignments (within fee-setup.view / .manage)
    Route::get('institutes/fees/student-assignments', [FeeController::class, 'indexStudentAssignments'])
        ->middleware('permission:fee-setup.view')
        ->name('institutes.fees.student-assignments.index');
    Route::post('institutes/fees/student-assignments', [FeeController::class, 'storeStudentAssignment'])
        ->middleware('permission:fee-setup.manage')
        ->name('institutes.fees.student-assignments.store');
    Route::delete('institutes/fees/student-assignments/{assignmentId}', [FeeController::class, 'destroyStudentAssignment'])
        ->middleware('permission:fee-setup.manage')
        ->name('institutes.fees.student-assignments.destroy');

    // API 4: Smart Bulk Voucher Generation (fee-vouchers.view / .generate / .delete)
    Route::get('institutes/fees/generate-vouchers', [FeeController::class, 'indexGenerateVouchers'])
        ->middleware('permission:fee-vouchers.view')
        ->name('institutes.fees.generate-vouchers.index');
    Route::post('institutes/fees/generate-vouchers', [FeeController::class, 'generateVouchers'])
        ->middleware('permission:fee-vouchers.generate')
        ->name('institutes.fees.generate-vouchers');
    Route::delete('institutes/fees/generate-vouchers', [FeeController::class, 'destroyVouchers'])
        ->middleware('permission:fee-vouchers.delete')
        ->name('institutes.fees.generate-vouchers.destroy');
    Route::delete('institutes/fees/generate-vouchers/{voucherId}', [FeeController::class, 'destroyVoucher'])
        ->middleware('permission:fee-vouchers.delete')
        ->name('institutes.fees.generate-vouchers.destroySingle');
    Route::delete('institutes/fees/generate-vouchers/batch/{batchId}', [FeeController::class, 'destroyVouchersByBatch'])
        ->middleware('permission:fee-vouchers.delete')
        ->name('institutes.fees.generate-vouchers.destroyBatch');
    Route::delete('institutes/fees/vouchers', [FeeController::class, 'destroyVouchers'])
        ->middleware('permission:fee-vouchers.delete')
        ->name('institutes.fees.vouchers.destroyBulk');
    Route::delete('institutes/fees/vouchers/{voucherId}', [FeeController::class, 'destroyVoucher'])
        ->middleware('permission:fee-vouchers.delete')
        ->name('institutes.fees.vouchers.destroy');
    Route::delete('institutes/fees/vouchers/batch/{batchId}', [FeeController::class, 'destroyVouchersByBatch'])
        ->middleware('permission:fee-vouchers.delete')
        ->name('institutes.fees.vouchers.destroyBatch');

    // API 5: Fetch Student Ledger (Cashier Search)
    Route::get('institutes/fees/ledger', [FeeController::class, 'ledger'])
        ->middleware('permission:fee-collections.collect')
        ->name('institutes.fees.ledger');

    Route::get('institutes/fees/records', [FeeController::class, 'records'])
        ->middleware('permission:fee-vouchers.view')
        ->name('institutes.fees.records');

    // Fee reports (fee-collections.reports)
    Route::middleware('permission:fee-collections.reports')->group(function () {
        Route::get('institutes/fees/reports/collections', [FeeReportController::class, 'collections'])
            ->name('institutes.fees.reports.collections');
        Route::get('institutes/fees/reports/defaulters', [FeeReportController::class, 'defaulters'])
            ->name('institutes.fees.reports.defaulters');
        Route::get('institutes/fees/reports/class-recovery', [FeeReportController::class, 'classRecovery'])
            ->name('institutes.fees.reports.class-recovery');
        Route::get('institutes/fees/reports/category-summary', [FeeReportController::class, 'categorySummary'])
            ->name('institutes.fees.reports.category-summary');
    });

    Route::get('institutes/fees/student-ledger/{student_id}', [FeeReportController::class, 'studentLedger'])
        ->middleware('permission:fee-vouchers.view')
        ->whereNumber('student_id')->name('institutes.fees.student-ledger.show');

    // API 5A: Fetch one student's voucher ledger and summary.
    Route::get('institutes/fees/student-ledger', [FeeController::class, 'studentLedger'])
        ->middleware('permission:fee-vouchers.view')
        ->name('institutes.fees.student-ledger');

    // API 5B: Fetch student's fee vouchers list
    Route::get('institutes/fees/student-vouchers', [FeeController::class, 'studentVouchers'])
        ->middleware('permission:fee-vouchers.view')
        ->name('institutes.fees.student-vouchers');
    Route::get('institutes/fees/vouchers', [FeeController::class, 'studentVouchers'])
        ->middleware('permission:fee-vouchers.view')
        ->name('institutes.fees.vouchers.index');

    // API 6: Collect Payment
    Route::post('institutes/fees/collect', [FeeController::class, 'collect'])
        ->middleware('permission:fee-collections.collect')
        ->name('institutes.fees.collect');

    // =====================================================================
    // Module 7: Timetable Generator & Scheduling
    // (timetable.view / .manage / .generate)
    // =====================================================================

    Route::middleware('permission:timetable.manage')->group(function () {
        // Step 1: Shifts & Time Slots Setup (Admin Period Duration + Standard Days & Friday Timings)
        Route::post('institutes/timetable/shifts', [TimetableController::class, 'setupShifts'])
            ->name('institutes.timetable.shifts.setup');
        Route::post('institutes/timetable/setup-slots', [TimetableController::class, 'setupSlots'])
            ->name('institutes.timetable.slots.setup');

        // Step 2: Subject Weightage (Curriculum per Class/Grade)
        Route::post('institutes/timetable/curriculum', [TimetableController::class, 'saveCurriculum'])
            ->name('institutes.timetable.curriculum.save');

        // Step 3 / Unified All-In-One Wizard: Step 1 (Timing) + Step 2 (Curriculum) + Step 3 (Generate)
        Route::post('institutes/timetable/wizard-generate', [TimetableController::class, 'setupAndGenerate'])
            ->name('institutes.timetable.wizard.generate');

        // Individual Slot CRUD
        Route::post('institutes/timetable/slots', [TimetableController::class, 'storeSlot'])
            ->name('institutes.timetable.slots.store');
        Route::put('institutes/timetable/slots/{timeSlot}', [TimetableController::class, 'updateSlot'])
            ->name('institutes.timetable.slots.update');
        Route::delete('institutes/timetable/slots/{timeSlot}', [TimetableController::class, 'destroySlot'])
            ->name('institutes.timetable.slots.destroy');
        Route::post('institutes/timetable/slots/preset', [TimetableController::class, 'seedPresetSlots'])
            ->name('institutes.timetable.slots.preset');

        Route::post('institutes/timetable/swap', [TimetableController::class, 'swap'])
            ->name('institutes.timetable.swap');
    });

    // Step 2 (read side): Subject Weightage (Curriculum per Class/Grade)
    Route::get('institutes/timetable/curriculum', [TimetableController::class, 'getCurriculum'])
        ->middleware('permission:timetable.view')
        ->name('institutes.timetable.curriculum.get');

    Route::get('institutes/timetable/slots', [TimetableController::class, 'indexSlots'])
        ->middleware('permission:timetable.view')
        ->name('institutes.timetable.slots.index');

    // Core Generator
    Route::post('institutes/timetable/generate', [TimetableController::class, 'generate'])
        ->middleware('permission:timetable.generate')
        ->name('institutes.timetable.generate');

    Route::middleware('permission:timetable.view')->group(function () {
        Route::get('institutes/timetable/class', [TimetableController::class, 'classSchedule'])
            ->name('institutes.timetable.class');
        Route::get('institutes/timetable/teacher', [TimetableController::class, 'teacherSchedule'])
            ->name('institutes.timetable.teacher');
        Route::get('institutes/timetable/master', [TimetableController::class, 'masterSchedule'])
            ->name('institutes.timetable.master');
        Route::get('institutes/timetable/export', [TimetableController::class, 'export'])
            ->name('institutes.timetable.export');
    });

    // Own timetable + own students' fee status: no module permission required.
    Route::get('institutes/teacher/timetable', [TimetableController::class, 'currentTeacherSchedule'])
        ->name('institutes.teacher.timetable.current');
    Route::get('institutes/teacher/students/fee-status', [TeacherFeeStatusController::class, 'index'])
        ->name('institutes.teacher.students.fee-status');

    // =====================================================================
    // Dashboard (dashboard.view)
    // =====================================================================

    Route::get('institutes/dashboard/summary', [DashboardController::class, 'summary'])
        ->middleware('permission:dashboard.view')
        ->name('institutes.dashboard.summary');

    Route::get('institutes/current', [InstituteController::class, 'currentInstitute'])
        ->middleware('permission:institute.view')
        ->name('institutes.current');

    // Subscription APIs for the institute frontend/mobile app.
    Route::get('institutes/plans', [SubscriptionController::class, 'plans'])
        ->middleware('permission:subscription.view')
        ->name('institutes.plans.index');
    Route::get('institutes/subscription', [SubscriptionController::class, 'current'])
        ->middleware('permission:subscription.view')
        ->name('institutes.subscription.current');
    Route::get('institutes/subscription/invoices', [SubscriptionController::class, 'invoices'])
        ->middleware('permission:subscription.view')
        ->name('institutes.subscription.invoices.index');
    Route::post('institutes/subscription/upgrade', [SubscriptionController::class, 'upgrade'])
        ->middleware('permission:subscription.manage')
        ->name('institutes.subscription.upgrade');
    Route::post('institutes/subscription/invoices/{invoice}/payment', [SubscriptionController::class, 'submitPayment'])
        ->middleware('permission:subscription.manage')
        ->name('institutes.subscription.invoices.payment');
    Route::post('institutes/subscription/invoices/{invoice}/cancel', [SubscriptionController::class, 'cancelInvoice'])
        ->middleware('permission:subscription.manage')
        ->name('institutes.subscription.invoices.cancel');

    // =====================================================================
    // Institutes (institute.view / .update)
    // =====================================================================

    Route::apiResource('institutes', InstituteController::class)
        ->only(['index', 'show'])->middleware('permission:institute.view');

    // Creating the first institute must work before the user has an active
    // institute membership (the store action creates that membership). The
    // route remains behind auth and email verification from the parent group.
    Route::apiResource('institutes', InstituteController::class)
        ->only(['store']);

    Route::middleware('permission:institute.update')->group(function () {
        Route::apiResource('institutes', InstituteController::class)
            ->only(['update', 'destroy']);
    });
});
