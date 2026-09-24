<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\AttendanceRecordsRequest;
use App\Http\Requests\Institute\AttendanceRosterRequest;
use App\Http\Requests\Institute\GetAttendanceRequest;
use App\Http\Requests\Institute\StoreAttendanceRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\RoomTeacher;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAllocation;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(GetAttendanceRequest $request)
    {
        $institute = $this->activeInstitute($request);

        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $sessionId = $validated['session_id'] ?? $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        $error = $this->attendanceScopeError($institute, $validated);

        if ($error !== null) {
            return $error;
        }

        $date = $validated['date'];

        $enrollments = Enrollment::query()
            ->with('student')
            ->where('session_id', $sessionId)
            ->where('class_id', $validated['class_id'])
            ->when(array_key_exists('section_id', $validated), fn ($query) => $this->applyNullableScope($query, 'section_id', $validated['section_id']))
            ->whereHas('student', fn ($query) => $query->where('institute_id', $institute->id))
            ->orderBy('roll_number')
            ->get();

        $attendances = Attendance::query()
            ->with('markedBy:id,name')
            ->where('session_id', $sessionId)
            ->where('class_id', $validated['class_id'])
            ->when(array_key_exists('section_id', $validated), fn ($query) => $this->applyNullableScope($query, 'section_id', $validated['section_id']))
            ->when(
                $institute->attendance_mode === 'class',
                fn ($query) => $query->whereNull('subject_id'),
                fn ($query) => $query->where('subject_id', $validated['subject_id'] ?? null)
            )
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        $presentCount = 0;
        $absentCount = 0;
        $lateCount = 0;
        $leaveCount = 0;
        $unmarkedCount = 0;

        $records = $enrollments->map(function (Enrollment $enrollment) use ($attendances, &$presentCount, &$absentCount, &$lateCount, &$leaveCount, &$unmarkedCount) {
            /** @var Attendance|null $attendance */
            $attendance = $attendances->get($enrollment->student_id);
            $status = $attendance?->status;

            match ($status) {
                'present' => $presentCount++,
                'absent' => $absentCount++,
                'late' => $lateCount++,
                'leave' => $leaveCount++,
                default => $unmarkedCount++,
            };

            return [
                'student_id' => $enrollment->student_id,
                'roll_number' => $enrollment->roll_number,
                'first_name' => $enrollment->student?->first_name,
                'last_name' => $enrollment->student?->last_name,
                'full_name' => trim(($enrollment->student?->first_name ?? '').' '.($enrollment->student?->last_name ?? '')),
                'gender' => $enrollment->student?->gender,
                'guardian_name' => $enrollment->student?->guardian_name,
                'guardian_phone' => $enrollment->student?->guardian_phone,
                'attendance_id' => $attendance?->id,
                'status' => $status,
                'marked_by' => $attendance?->markedBy ? [
                    'id' => $attendance->markedBy->id,
                    'name' => $attendance->markedBy->name,
                ] : null,
                'marked_at' => $attendance?->updated_at?->toISOString() ?? $attendance?->created_at?->toISOString(),
            ];
        });

        $academicClass = AcademicClass::find($validated['class_id']);
        $section = isset($validated['section_id']) ? AcademicSection::find($validated['section_id']) : null;
        $subject = isset($validated['subject_id']) ? Subject::find($validated['subject_id']) : null;

        $totalStudents = $enrollments->count();

        return ResponseService::success([
            'attendance_mode' => $institute->attendance_mode,
            'session_id' => (int) $sessionId,
            'date' => $date,
            'class' => [
                'id' => $academicClass->id,
                'name' => $academicClass->name,
            ],
            'section' => $section ? [
                'id' => $section->id,
                'name' => $section->name,
            ] : null,
            'subject' => $subject ? [
                'id' => $subject->id,
                'name' => $subject->name,
            ] : null,
            'summary' => [
                'total_students' => $totalStudents,
                'present_count' => $presentCount,
                'absent_count' => $absentCount,
                'late_count' => $lateCount,
                'leave_count' => $leaveCount,
                'unmarked_count' => $unmarkedCount,
                'is_fully_marked' => $totalStudents > 0 && $unmarkedCount === 0,
            ],
            'records' => $records->values(),
        ], 'Attendance retrieved successfully');
    }

    public function tasks(Request $request)
    {
        $institute = $this->activeInstitute($request);

        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        $tasks = $institute->attendance_mode === 'class'
            ? RoomTeacher::query()
                ->with(['academicClass', 'section'])
                ->where('session_id', $sessionId)
                ->where('teacher_user_id', $request->user()->id)
                ->get()
                ->map(fn (RoomTeacher $task) => $this->taskData($task->academicClass, $task->section))
            : SubjectAllocation::query()
                ->with(['academicClass', 'section', 'subject'])
                ->where('session_id', $sessionId)
                ->where('teacher_user_id', $request->user()->id)
                ->get()
                ->map(fn (SubjectAllocation $task) => [
                    ...$this->taskData($task->academicClass, $task->section),
                    'subject_id' => $task->subject_id,
                    'subject_name' => $task->subject->name,
                ]);

        return ResponseService::success([
            'attendance_type' => $institute->attendance_mode,
            'date' => $request->input('date', now()->toDateString()),
            'tasks' => $tasks->values(),
        ], 'Attendance tasks retrieved successfully');
    }

    public function roster(AttendanceRosterRequest $request)
    {
        $institute = $this->activeInstitute($request);

        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        $validated = $request->validated();
        $error = $this->attendanceScopeError($institute, $validated);

        if ($error !== null) {
            return $error;
        }

        $enrollments = Enrollment::query()
            ->with('student')
            ->where('session_id', $sessionId)
            ->where('class_id', $validated['class_id'])
            ->when(array_key_exists('section_id', $validated), fn ($query) => $this->applyNullableScope($query, 'section_id', $validated['section_id']))
            ->whereHas('student', fn ($query) => $query->where('institute_id', $institute->id))
            ->orderBy('roll_number')
            ->get();

        $attendances = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $validated['class_id'])
            ->when(array_key_exists('section_id', $validated), fn ($query) => $this->applyNullableScope($query, 'section_id', $validated['section_id']))
            ->when(array_key_exists('subject_id', $validated), fn ($query) => $this->applyNullableScope($query, 'subject_id', $validated['subject_id']))
            ->where('date', $validated['date'])
            ->pluck('status', 'student_id');

        return ResponseService::success($enrollments->map(fn (Enrollment $enrollment) => [
            'student_id' => $enrollment->student_id,
            'roll_number' => $enrollment->roll_number,
            'first_name' => $enrollment->student->first_name,
            'last_name' => $enrollment->student->last_name,
            'status' => $attendances->get($enrollment->student_id),
        ])->values(), 'Attendance roster retrieved successfully');
    }

    public function store(StoreAttendanceRequest $request)
    {
        $institute = $this->activeInstitute($request);

        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        $validated = $request->validated();
        $error = $this->attendanceScopeError($institute, $validated);

        if ($error !== null) {
            return $error;
        }

        if (! $this->canMarkAttendance($request, $institute, $sessionId, $validated)) {
            return ResponseService::error('Forbidden', 403, ['attendance' => ['You are not assigned to mark attendance for this scope.']]);
        }

        $studentIds = collect($validated['attendances'])->pluck('student_id');
        $enrolledStudentCount = Enrollment::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $validated['class_id'])
            ->when(array_key_exists('section_id', $validated), fn ($query) => $this->applyNullableScope($query, 'section_id', $validated['section_id']))
            ->whereIn('student_id', $studentIds)
            ->count();

        if ($enrolledStudentCount !== $studentIds->count()) {
            return ResponseService::error('Validation failed', 422, ['attendances' => ['Every student must be enrolled in the selected class and section in the active session.']]);
        }

        DB::transaction(function () use ($validated, $sessionId, $request) {
            foreach ($validated['attendances'] as $attendance) {
                Attendance::query()->updateOrCreate(
                    [
                        'session_id' => $sessionId,
                        'class_id' => $validated['class_id'],
                        'section_id' => $validated['section_id'] ?? null,
                        'subject_id' => $validated['subject_id'] ?? null,
                        'student_id' => $attendance['student_id'],
                        'date' => $validated['date'],
                    ],
                    [
                        'status' => $attendance['status'],
                        'marked_by_user_id' => $request->user()->id,
                    ]
                );
            }
        });

        return ResponseService::success(['saved_count' => count($validated['attendances'])], 'Attendance saved successfully');
    }

    public function records(AttendanceRecordsRequest $request)
    {
        $institute = $this->activeInstitute($request);

        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $error = $this->recordsScopeError($institute, $validated);

        if ($error !== null) {
            return $error;
        }

        $sessionId = $validated['session_id'] ?? $this->activeSessionId($institute->id);
        $status = $validated['status'] ?? null;
        $search = trim((string) ($validated['search'] ?? '')) !== '' ? $validated['search'] : null;

        $query = Attendance::query()
            ->with([
                'session:id,name',
                'academicClass:id,name',
                'section:id,name',
                'subject:id,name',
                'markedBy:id,name',
                'student:id,institute_id,first_name,last_name,gender',
                'student.enrollments:id,student_id,session_id,roll_number',
            ])
            ->whereHas('student', function ($query) use ($institute, $search) {
                $query->where('institute_id', $institute->id);

                if ($search !== null) {
                    $query->where(function ($query) use ($search) {
                        $query->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhereHas('enrollments', fn ($query) => $query->where('roll_number', 'like', '%'.$search.'%'));
                    });
                }
            })
            ->when($sessionId !== null, fn ($query) => $query->where('session_id', $sessionId))
            ->when(array_key_exists('class_id', $validated) && $validated['class_id'] !== null, fn ($query) => $query->where('class_id', $validated['class_id']))
            ->when(array_key_exists('section_id', $validated), fn ($query) => $this->applyNullableScope($query, 'section_id', $validated['section_id']))
            ->when(array_key_exists('subject_id', $validated), fn ($query) => $this->applyNullableScope($query, 'subject_id', $validated['subject_id']))
            ->when(array_key_exists('student_id', $validated) && $validated['student_id'] !== null, fn ($query) => $query->where('student_id', $validated['student_id']))
            ->when($status !== null, fn ($query) => $query->where('status', $status));

        if (array_key_exists('date', $validated) && $validated['date'] !== null) {
            $query->whereDate('date', $validated['date']);
        } elseif (array_key_exists('date_from', $validated) || array_key_exists('date_to', $validated)) {
            $query->whereDate('date', '>=', $validated['date_from'] ?? '1900-01-01');
            $query->whereDate('date', '<=', $validated['date_to'] ?? now()->toDateString());
        }

        $summary = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $records = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $records->transform(function (Attendance $attendance) {
            $student = $attendance->student;
            $rollNumber = $student
                ? $student->enrollments->first(fn ($enrollment) => (int) $enrollment->session_id === (int) $attendance->session_id)?->roll_number
                : null;

            return [
                'id' => $attendance->id,
                'date' => $attendance->date?->toDateString(),
                'day_of_week' => $attendance->date?->format('l'),
                'status' => $attendance->status,
                'session' => $attendance->session ? [
                    'id' => $attendance->session->id,
                    'name' => $attendance->session->name,
                ] : null,
                'class' => $attendance->academicClass ? [
                    'id' => $attendance->academicClass->id,
                    'name' => $attendance->academicClass->name,
                ] : null,
                'section' => $attendance->section ? [
                    'id' => $attendance->section->id,
                    'name' => $attendance->section->name,
                ] : null,
                'subject' => $attendance->subject ? [
                    'id' => $attendance->subject->id,
                    'name' => $attendance->subject->name,
                ] : null,
                'student' => $student ? [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'full_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                    'gender' => $student->gender,
                    'roll_number' => $rollNumber,
                ] : null,
                'marked_by' => $attendance->markedBy ? [
                    'id' => $attendance->markedBy->id,
                    'name' => $attendance->markedBy->name,
                ] : null,
                'marked_at' => ($attendance->updated_at ?? $attendance->created_at)?->toIso8601String(),
            ];
        });

        return ResponseService::success([
            'attendance_mode' => $institute->attendance_mode,
            'filters' => [
                'session_id' => $sessionId,
                'class_id' => $validated['class_id'] ?? null,
                'section_id' => $validated['section_id'] ?? null,
                'subject_id' => $validated['subject_id'] ?? null,
                'student_id' => $validated['student_id'] ?? null,
                'status' => $status,
                'date' => $validated['date'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'search' => $search,
            ],
            'summary' => [
                'total_records' => $summary->sum(),
                'present_count' => (int) $summary->get('present', 0),
                'absent_count' => (int) $summary->get('absent', 0),
                'late_count' => (int) $summary->get('late', 0),
                'leave_count' => (int) $summary->get('leave', 0),
            ],
            'records' => $records->values(),
        ], 'Attendance records retrieved successfully');
    }

    private function recordsScopeError(Institute $institute, array $validated): ?JsonResponse
    {
        if (isset($validated['session_id']) && ! AcademicSession::query()->whereKey($validated['session_id'])->where('institute_id', $institute->id)->exists()) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['The selected session does not belong to the active institute.']]);
        }

        if (isset($validated['class_id']) && ! AcademicClass::query()->whereKey($validated['class_id'])->where('institute_id', $institute->id)->exists()) {
            return ResponseService::error('Validation failed', 422, ['class_id' => ['The selected class does not belong to the active institute.']]);
        }

        if (isset($validated['section_id'])) {
            $section = AcademicSection::find($validated['section_id']);
            $classId = $validated['class_id'] ?? null;

            if ($section === null || ($classId !== null && $section->class_id !== (int) $classId)) {
                return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section does not belong to the selected class.']]);
            }

            if ($classId === null && ! AcademicClass::query()->whereKey($section->class_id)->where('institute_id', $institute->id)->exists()) {
                return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section does not belong to the active institute.']]);
            }
        }

        if (isset($validated['subject_id']) && ! Subject::query()->whereKey($validated['subject_id'])->where('institute_id', $institute->id)->exists()) {
            return ResponseService::error('Validation failed', 422, ['subject_id' => ['The selected subject does not belong to the active institute.']]);
        }

        if (isset($validated['student_id']) && ! Student::query()->whereKey($validated['student_id'])->where('institute_id', $institute->id)->exists()) {
            return ResponseService::error('Validation failed', 422, ['student_id' => ['The selected student does not belong to the active institute.']]);
        }

        return null;
    }

    private function attendanceScopeError(Institute $institute, array $validated): ?JsonResponse
    {
        if (! AcademicClass::query()->whereKey($validated['class_id'])->where('institute_id', $institute->id)->exists()) {
            return ResponseService::error('Validation failed', 422, ['class_id' => ['The selected class does not belong to the active institute.']]);
        }

        if (($validated['section_id'] ?? null) !== null && ! AcademicSection::query()
            ->whereKey($validated['section_id'])
            ->where('class_id', $validated['class_id'])
            ->exists()) {
            return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section does not belong to the selected class.']]);
        }

        $subjectId = $validated['subject_id'] ?? null;

        if ($institute->attendance_mode === 'class' && $subjectId !== null) {
            return ResponseService::error('Validation failed', 422, ['subject_id' => ['Subject attendance is not available for a class-based institute.']]);
        }

        if ($institute->attendance_mode === 'subject' && $subjectId === null) {
            return ResponseService::error('Validation failed', 422, ['subject_id' => ['A subject is required for a subject-based institute.']]);
        }

        if ($subjectId !== null && ! Subject::query()->whereKey($subjectId)->where('institute_id', $institute->id)->exists()) {
            return ResponseService::error('Validation failed', 422, ['subject_id' => ['The selected subject does not belong to the active institute.']]);
        }

        return null;
    }

    private function canMarkAttendance(Request $request, Institute $institute, int $sessionId, array $validated): bool
    {
        if ($this->isInstituteAttendanceAdmin($request, $institute)) {
            return true;
        }

        $scope = [
            'session_id' => $sessionId,
            'class_id' => $validated['class_id'],
            'section_id' => $validated['section_id'] ?? null,
            'teacher_user_id' => $request->user()->id,
        ];

        if ($institute->attendance_mode === 'class') {
            return RoomTeacher::query()->where($scope)->exists();
        }

        return SubjectAllocation::query()->where([...$scope, 'subject_id' => $validated['subject_id']])->exists();
    }

    private function isInstituteAttendanceAdmin(Request $request, Institute $institute): bool
    {
        return $institute->user_id === $request->user()->id
            || InstituteUser::query()
                ->where('institute_id', $institute->id)
                ->where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->where('is_owner', true)
                ->exists()
            || $request->user()->hasRole('admin');
    }

    private function taskData(AcademicClass $academicClass, ?AcademicSection $section): array
    {
        return [
            'class_id' => $academicClass->id,
            'section_id' => $section?->id,
            'class_name' => trim($academicClass->name.' - '.($section?->name ?? '')),
        ];
    }

    private function activeInstitute(Request $request): ?Institute
    {
        $instituteId = InstituteUser::query()->where('user_id', $request->user()->id)->where('is_active', true)->value('institute_id');

        return $instituteId === null ? null : Institute::find($instituteId);
    }

    private function activeSessionId(int $instituteId): ?int
    {
        $sessionId = AcademicSession::query()->where('institute_id', $instituteId)->where('is_active', true)->value('id');

        return $sessionId === null ? null : (int) $sessionId;
    }

    private function applyNullableScope($query, string $column, ?int $value): void
    {
        $value === null ? $query->whereNull($column) : $query->where($column, $value);
    }
}
