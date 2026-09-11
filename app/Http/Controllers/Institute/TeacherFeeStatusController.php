<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeVoucher;
use App\Models\InstituteUser;
use App\Models\RoomTeacher;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class TeacherFeeStatusController extends Controller
{
    /**
     * Return fee statuses only for students in the authenticated teacher's rooms.
     */
    public function index(Request $request)
    {
        $request->validate([
            'billing_month' => ['nullable', 'date_format:Y-m'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->value('id');

        if ($sessionId === null) {
            return ResponseService::error('No active academic session exists for the active institute', 422);
        }

        $rooms = RoomTeacher::query()
            ->where('session_id', $sessionId)
            ->where('teacher_user_id', $request->user()->id)
            ->with(['academicClass:id,name,code', 'section:id,name,code'])
            ->get();

        $billingMonth = $request->input('billing_month') ?? now()->format('Y-m');

        if ($rooms->isEmpty()) {
            return ResponseService::success([
                'session_id' => $sessionId,
                'billing_month' => $billingMonth,
                'assigned_rooms' => [],
                'summary' => ['total_students' => 0, 'paid_students' => 0, 'unpaid_students' => 0, 'not_generated_students' => 0],
                'students' => [],
                'pagination' => null,
            ], 'No rooms are assigned to the current teacher');
        }

        $roomFilter = function ($query) use ($rooms) {
            foreach ($rooms as $room) {
                $query->orWhere(function ($roomQuery) use ($room) {
                    $roomQuery->where('class_id', $room->class_id);

                    if ($room->section_id !== null) {
                        $roomQuery->where('section_id', $room->section_id);
                    }
                });
            }
        };

        $enrollmentQuery = Enrollment::query()
            ->where('session_id', $sessionId)
            ->where($roomFilter);

        $assignedStudentIds = (clone $enrollmentQuery)->pluck('student_id');

        $vouchers = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->where('billing_month', $billingMonth)
            ->whereIn('student_id', $assignedStudentIds)
            ->get();

        $enrollments = $enrollmentQuery
            ->with(['student:id,first_name,last_name,guardian_name,guardian_phone', 'academicClass:id,name,code', 'section:id,name,code'])
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->orderBy('roll_number')
            ->paginate($request->integer('per_page', 50));

        $vouchers = $vouchers->keyBy('student_id');

        $students = $enrollments->getCollection()->map(function (Enrollment $enrollment) use ($vouchers) {
            $voucher = $vouchers->get($enrollment->student_id);
            $voucherStatus = $voucher?->status;
            $feeStatus = $voucherStatus === 'paid' ? 'paid' : ($voucher ? 'unpaid' : 'not_generated');

            return [
                'student_id' => $enrollment->student_id,
                'name' => trim($enrollment->student->first_name.' '.$enrollment->student->last_name),
                'roll_number' => $enrollment->roll_number,
                'guardian_name' => $enrollment->student->guardian_name,
                'guardian_phone' => $enrollment->student->guardian_phone,
                'class' => $enrollment->academicClass ? ['id' => $enrollment->academicClass->id, 'name' => $enrollment->academicClass->name, 'code' => $enrollment->academicClass->code] : null,
                'section' => $enrollment->section ? ['id' => $enrollment->section->id, 'name' => $enrollment->section->name, 'code' => $enrollment->section->code] : null,
                'fee_status' => $feeStatus,
                'voucher_status' => $voucherStatus,
                'voucher_id' => $voucher?->id,
                'due_date' => $voucher?->due_date?->toDateString(),
                'total_amount' => $voucher?->total_amount ?? 0,
                'paid_amount' => $voucher?->paid_amount ?? 0,
                'balance_due' => $voucher ? $voucher->balance_due : 0,
            ];
        });

        $enrollments->setCollection($students);

        return ResponseService::success([
            'session_id' => $sessionId,
            'billing_month' => $billingMonth,
            'assigned_rooms' => $rooms->map(fn (RoomTeacher $room) => [
                'class' => $room->academicClass ? ['id' => $room->academicClass->id, 'name' => $room->academicClass->name, 'code' => $room->academicClass->code] : null,
                'section' => $room->section ? ['id' => $room->section->id, 'name' => $room->section->name, 'code' => $room->section->code] : null,
            ])->values(),
            'summary' => [
                'total_students' => $assignedStudentIds->unique()->count(),
                'paid_students' => $vouchers->where('status', 'paid')->count(),
                'unpaid_students' => $vouchers->where('status', '!=', 'paid')->count(),
                'not_generated_students' => $assignedStudentIds->unique()->count() - $vouchers->count(),
            ],
            'students' => $students->values(),
            'pagination' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ], 'Teacher student fee statuses retrieved successfully');
    }
}
