<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\AttendanceAbsenteesRequest;
use App\Http\Requests\Institute\AttendanceClassSummaryRequest;
use App\Http\Requests\Institute\AttendanceMonthlyRegisterRequest;
use App\Http\Requests\Institute\AttendanceOverviewRequest;
use App\Http\Requests\Institute\AttendanceStudentReportRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AttendanceReportController extends Controller
{
    /**
     * Statuses that can be combined into a single day-level status, most severe first.
     */
    private const DAY_STATUS_PRECEDENCE = ['absent', 'leave', 'late', 'present'];

    public function overview(AttendanceOverviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->context($request, (int) $validated['session_id']);
        if ($context instanceof JsonResponse) return $context;
        [$institute, $session] = $context;

        $date = $validated['date'];
        $groups = Enrollment::query()->with(['academicClass', 'section'])
            ->where('session_id', $session->id)
            ->whereHas('student', fn ($query) => $query->where('institute_id', $institute->id))
            ->get()
            ->groupBy(fn (Enrollment $enrollment) => $enrollment->class_id.':'.($enrollment->section_id ?? 'null'));

        $overall = ['total_students' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'unmarked' => 0];
        $classes = [];

        foreach ($groups as $enrollments) {
            $first = $enrollments->first();

            $rows = $this->reportAttendanceQuery($session->id, (int) $first->class_id, $first->section_id, null, $institute->attendance_mode)
                ->when($first->section_id === null, fn (Builder $query) => $query->whereNull('section_id'))
                ->whereDate('date', $date)
                ->with('markedBy:id,name')
                ->get();
            $rowsByStudentDate = $this->indexRows($rows);

            $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'unmarked' => 0];
            foreach ($enrollments as $enrollment) {
                $dayRows = $rowsByStudentDate->get($enrollment->student_id, collect())->get($date, collect());
                $status = $this->dayStatus($dayRows);
                $counts[$status ?? 'unmarked']++;
            }

            $marked = $rows->first();
            $classes[] = [
                'class_id' => $first->class_id,
                'class_name' => $first->academicClass?->name,
                'section_id' => $first->section_id,
                'section_name' => $first->section?->name,
                'is_marked' => $counts['unmarked'] === 0,
                'total' => $enrollments->count(),
                'present' => $counts['present'],
                'absent' => $counts['absent'],
                'late' => $counts['late'],
                'leave' => $counts['leave'],
                'unmarked' => $counts['unmarked'],
                'marked_by' => $marked?->markedBy?->name,
                'marked_at' => $marked?->updated_at?->format('h:i A'),
            ];

            $overall['total_students'] += $enrollments->count();
            foreach ($counts as $key => $value) $overall[$key] += $value;
        }

        $overall['percentage'] = $this->percentage($overall);

        return ResponseService::success([
            'date' => $date,
            'overall' => $overall,
            'classes_status' => $classes,
        ], 'Attendance overview retrieved successfully');
    }

    public function absentees(AttendanceAbsenteesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->context($request, (int) $validated['session_id']);
        if ($context instanceof JsonResponse) return $context;
        [$institute, $session] = $context;
        if ($error = $this->scopeError($institute, $validated)) return $error;

        $date = $validated['date'];
        $status = $validated['status'] ?? 'absent';

        $enrollments = $this->enrollments($institute, $session->id, $validated)
            ->with(['student', 'academicClass', 'section'])
            ->get();

        $rowsByStudentDate = $this->indexRows(
            Attendance::query()
                ->where('session_id', $session->id)
                ->when($institute->attendance_mode === 'class', fn (Builder $query) => $query->whereNull('subject_id'))
                ->when(isset($validated['class_id']), fn (Builder $query) => $query->where('class_id', $validated['class_id']))
                ->when(isset($validated['section_id']), fn (Builder $query) => $query->where('section_id', $validated['section_id']))
                ->whereDate('date', '<=', $date)
                ->get()
        );

        $records = [];
        foreach ($enrollments as $enrollment) {
            $days = $rowsByStudentDate->get($enrollment->student_id, collect());
            if ($this->dayStatus($days->get($date, collect())) !== $status) continue;

            $consecutive = $this->consecutiveStatusDays($days, $date, $status);
            if (isset($validated['consecutive_days']) && $consecutive < (int) $validated['consecutive_days']) continue;

            $records[] = [
                'student_id' => $enrollment->student_id,
                'roll_number' => $enrollment->roll_number,
                'full_name' => $this->fullName($enrollment->student),
                'class_name' => $enrollment->academicClass?->name,
                'section_name' => $enrollment->section?->name,
                'guardian_name' => $enrollment->student?->guardian_name,
                'guardian_phone' => $enrollment->student?->guardian_phone,
                'status' => $status,
                'consecutive_absent_days' => $consecutive,
                'overall_session_percentage' => $this->percentage($this->dayCounts($days)),
            ];
        }

        return ResponseService::success([
            'total_count' => count($records),
            'records' => $records,
        ], 'Attendance absentees retrieved successfully');
    }

    public function monthlyRegister(AttendanceMonthlyRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->context($request, (int) $validated['session_id']);
        if ($context instanceof JsonResponse) return $context;
        [$institute, $session] = $context;
        if ($error = $this->scopeError($institute, $validated)) return $error;

        $from = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $enrollments = $this->sortedEnrollments(
            $this->enrollments($institute, $session->id, $validated)->with('student')->get()
        );

        $rows = $this->reportAttendanceQuery(
            $session->id,
            (int) $validated['class_id'],
            $validated['section_id'] ?? null,
            $validated['subject_id'] ?? null,
            $institute->attendance_mode
        )
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();
        $rowsByStudentDate = $this->indexRows($rows);

        $students = $enrollments->map(function (Enrollment $enrollment) use ($rowsByStudentDate) {
            $days = $rowsByStudentDate->get($enrollment->student_id, collect());
            $counts = $this->dayCounts($days);

            return [
                'student_id' => $enrollment->student_id,
                'roll_number' => $enrollment->roll_number,
                'full_name' => $this->fullName($enrollment->student),
                'guardian_name' => $enrollment->student?->guardian_name,
                'daily_status' => $days->map(fn (Collection $dayRows) => $this->dayStatus($dayRows))->all(),
                'summary' => [...$counts, 'percentage' => $this->percentage($counts)],
            ];
        })->values();

        $daysHeader = collect(range(1, $to->day))->map(function (int $day) use ($from) {
            $date = $from->copy()->day($day);

            return [
                'day' => $day,
                'date' => $date->toDateString(),
                'day_name' => $date->format('D'),
                'is_weekend' => $date->isWeekend(),
            ];
        })->values();

        return ResponseService::success([
            'month' => $validated['month'],
            'working_days_count' => $this->workingDays($rowsByStudentDate),
            'days_header' => $daysHeader,
            'students' => $students,
        ], 'Monthly attendance register retrieved successfully');
    }

    public function classSummary(AttendanceClassSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->context($request, (int) $validated['session_id']);
        if ($context instanceof JsonResponse) return $context;
        [$institute, $session] = $context;
        if ($error = $this->scopeError($institute, $validated)) return $error;

        $start = $validated['start_date'] ?? $session->start_date?->toDateString();
        $end = $validated['end_date'] ?? min(now()->toDateString(), $session->end_date?->toDateString() ?? now()->toDateString());

        $enrollments = $this->sortedEnrollments(
            $this->enrollments($institute, $session->id, $validated)->with('student')->get()
        );

        $rows = $this->reportAttendanceQuery(
            $session->id,
            (int) $validated['class_id'],
            $validated['section_id'] ?? null,
            $validated['subject_id'] ?? null,
            $institute->attendance_mode
        )
            ->when($start, fn (Builder $query) => $query->whereDate('date', '>=', $start))
            ->whereDate('date', '<=', $end)
            ->get();
        $rowsByStudentDate = $this->indexRows($rows);

        $students = $enrollments->map(function (Enrollment $enrollment) use ($rowsByStudentDate) {
            $counts = $this->dayCounts($rowsByStudentDate->get($enrollment->student_id, collect()));

            return [
                'student_id' => $enrollment->student_id,
                'roll_number' => $enrollment->roll_number,
                'full_name' => $this->fullName($enrollment->student),
                'guardian_phone' => $enrollment->student?->guardian_phone,
                ...$counts,
                'percentage' => $this->percentage($counts),
            ];
        });

        if (isset($validated['below_percentage'])) {
            $students = $students
                ->filter(fn (array $student) => $student['percentage'] < (float) $validated['below_percentage'])
                ->values();
        }

        return ResponseService::success([
            'total_working_days' => $this->workingDays($rowsByStudentDate),
            'students' => $students,
        ], 'Class attendance summary retrieved successfully');
    }

    public function student(AttendanceStudentReportRequest $request, int $studentId): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->context($request, (int) $validated['session_id']);
        if ($context instanceof JsonResponse) return $context;
        [$institute, $session] = $context;
        if ($error = $this->scopeError($institute, $validated)) return $error;

        $student = Student::query()->where('institute_id', $institute->id)->find($studentId);
        if (! $student) return ResponseService::notFound('Student not found in the active institute');

        $enrollment = Enrollment::query()->with(['academicClass', 'section'])
            ->where('student_id', $student->id)
            ->where('session_id', $session->id)
            ->first();
        if (! $enrollment) return ResponseService::notFound('Student is not enrolled in the selected session');

        $rowsByDate = Attendance::query()
            ->where('session_id', $session->id)
            ->where('student_id', $student->id)
            ->when($institute->attendance_mode === 'class', fn (Builder $query) => $query->whereNull('subject_id'))
            ->when($institute->attendance_mode === 'subject' && isset($validated['subject_id']), fn (Builder $query) => $query->where('subject_id', $validated['subject_id']))
            ->with('subject:id,name')
            ->orderBy('date')
            ->get()
            ->groupBy(fn (Attendance $attendance) => $attendance->date->toDateString());

        $monthlyTrend = $rowsByDate
            ->groupBy(fn (Collection $days, string $day) => Carbon::parse($day)->format('Y-m'))
            ->map(function (Collection $days, string $month) {
                $counts = $this->dayCounts($days);

                return [
                    'month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                    'working_days' => $days->count(),
                    ...$counts,
                    'percentage' => $this->percentage($counts),
                ];
            })->values();

        $sessionCounts = $this->dayCounts($rowsByDate);

        $logDays = isset($validated['month'])
            ? $rowsByDate->filter(fn (Collection $days, string $day) => str_starts_with($day, $validated['month'].'-'))
            : $rowsByDate;

        $dailyLogs = $logDays->map(function (Collection $dayRows, string $day) {
            $subjects = $dayRows->pluck('subject.name')->filter()->unique()->implode(', ');

            return [
                'date' => $day,
                'day' => Carbon::parse($day)->format('l'),
                'status' => $this->dayStatus($dayRows),
                'subject_name' => $subjects !== '' ? $subjects : null,
            ];
        })->values();

        return ResponseService::success([
            'student' => [
                'id' => $student->id,
                'full_name' => $this->fullName($student),
                'roll_number' => $enrollment->roll_number,
                'class_name' => $enrollment->academicClass?->name,
                'section_name' => $enrollment->section?->name,
            ],
            'session_summary' => [
                'total_working_days' => $rowsByDate->count(),
                ...$sessionCounts,
                'percentage' => $this->percentage($sessionCounts),
            ],
            'monthly_trend' => $monthlyTrend,
            'daily_logs' => $dailyLogs,
        ], 'Student attendance retrieved successfully');
    }

    private function context(Request $request, int $sessionId): array|JsonResponse
    {
        $instituteId = InstituteUser::query()->where('user_id', $request->user()->id)->where('is_active', true)->value('institute_id');
        $institute = $instituteId ? Institute::find($instituteId) : null;
        if (! $institute) return ResponseService::error('No active institute is associated with this user', 422);

        $session = AcademicSession::query()->where('institute_id', $institute->id)->find($sessionId);
        if (! $session) return ResponseService::error('Validation failed', 422, ['session_id' => ['The selected session does not belong to the active institute.']]);

        return [$institute, $session];
    }

    private function scopeError(Institute $institute, array $v): ?JsonResponse
    {
        if (isset($v['class_id']) && ! AcademicClass::query()->where('institute_id', $institute->id)->whereKey($v['class_id'])->exists()) return ResponseService::error('Validation failed', 422, ['class_id' => ['The selected class does not belong to the active institute.']]);
        if (isset($v['section_id'])) {
            $section = AcademicSection::find($v['section_id']);
            if (! $section || (isset($v['class_id']) && (int) $section->class_id !== (int) $v['class_id']) || ! AcademicClass::query()->where('institute_id', $institute->id)->whereKey($section->class_id)->exists()) return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section is outside the requested institute/class.']]);
        }
        if (isset($v['subject_id']) && ! Subject::query()->where('institute_id', $institute->id)->whereKey($v['subject_id'])->exists()) return ResponseService::error('Validation failed', 422, ['subject_id' => ['The selected subject does not belong to the active institute.']]);
        if ($institute->attendance_mode === 'class' && ! empty($v['subject_id'])) return ResponseService::error('Validation failed', 422, ['subject_id' => ['Subject attendance is not available for a class-based institute.']]);

        return null;
    }

    private function enrollments(Institute $institute, int $sessionId, array $v): Builder
    {
        return Enrollment::query()
            ->where('session_id', $sessionId)
            ->whereHas('student', fn (Builder $query) => $query->where('institute_id', $institute->id))
            ->when(isset($v['class_id']), fn (Builder $query) => $query->where('class_id', $v['class_id']))
            ->when(array_key_exists('section_id', $v), fn (Builder $query) => $v['section_id'] === null ? $query->whereNull('section_id') : $query->where('section_id', $v['section_id']));
    }

    /**
     * Sort enrollments by roll number numerically (the column is a string, so a plain
     * SQL sort would place roll 10 before roll 9) and fall back to student id.
     */
    private function sortedEnrollments(Collection $enrollments): Collection
    {
        return $enrollments->sortBy([
            [fn (Enrollment $enrollment) => (int) $enrollment->roll_number, 'asc'],
            [fn (Enrollment $enrollment) => (int) $enrollment->student_id, 'asc'],
        ])->values();
    }

    /**
     * A null $sectionId means "no section filter" so an omitted section_id covers every
     * section of the class. Callers that specifically need the section-less bucket (the
     * institute overview groups by class + section) add their own whereNull.
     */
    private function reportAttendanceQuery(int $sessionId, int $classId, ?int $sectionId, ?int $subjectId, string $mode): Builder
    {
        $query = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->when($sectionId !== null, fn (Builder $query) => $query->where('section_id', $sectionId));

        if ($mode === 'class') return $query->whereNull('subject_id');

        return $subjectId === null ? $query->whereNotNull('subject_id') : $query->where('subject_id', $subjectId);
    }

    /**
     * Group attendance rows into [student_id => [Y-m-d => rows]] so that subject-wise
     * institutes can collapse every subject marked on a day into a single day status.
     */
    private function indexRows(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (Attendance $attendance) => (int) $attendance->student_id)
            ->map(fn (Collection $studentRows) => $studentRows->groupBy(fn (Attendance $attendance) => $attendance->date->toDateString()));
    }

    /**
     * Collapse every row marked on a single day into one day-level status.
     * A subject-wise institute marks one row per subject, so "absent in maths" makes
     * the whole day absent. Returns null when the day was never marked.
     */
    private function dayStatus(Collection $dayRows): ?string
    {
        foreach (self::DAY_STATUS_PRECEDENCE as $status) {
            if ($dayRows->contains('status', $status)) return $status;
        }

        return null;
    }

    /**
     * @param  Collection<string, Collection<int, Attendance>>  $days
     * @return array{present: int, absent: int, late: int, leave: int}
     */
    private function dayCounts(Collection $days): array
    {
        $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];

        foreach ($days as $dayRows) {
            $status = $this->dayStatus($dayRows);
            if ($status !== null) $counts[$status]++;
        }

        return $counts;
    }

    /**
     * Percentage of marked days a student was present on, matching the formula used
     * across every attendance report (late days are counted separately, not as present).
     *
     * @param  array<string, int>  $counts
     */
    private function percentage(array $counts): float
    {
        $marked = ($counts['present'] ?? 0) + ($counts['absent'] ?? 0) + ($counts['late'] ?? 0) + ($counts['leave'] ?? 0);

        return $marked > 0 ? round((($counts['present'] ?? 0) / $marked) * 100, 2) : 0;
    }

    /**
     * Distinct calendar days that carry at least one attendance row, no matter which
     * student or subject was marked.
     *
     * @param  Collection<int|string, Collection<string, Collection<int, Attendance>>>  $rowsByStudentDate
     */
    private function workingDays(Collection $rowsByStudentDate): int
    {
        return $rowsByStudentDate
            ->map(fn (Collection $days) => $days->keys())
            ->flatten()
            ->unique()
            ->count();
    }

    /**
     * Count consecutive marked days, walking backwards from $date, that carry the given
     * status. Days with no marking at all (weekends, holidays) are skipped so they do
     * not break a genuine streak; a day marked with any other status ends the streak.
     *
     * @param  Collection<string, Collection<int, Attendance>>  $days
     */
    private function consecutiveStatusDays(Collection $days, string $date, string $status): int
    {
        $count = 0;

        foreach ($days->keys()->sortDesc() as $day) {
            if ($day > $date) continue;

            $dayStatus = $this->dayStatus($days->get($day));
            if ($dayStatus === null) continue;
            if ($dayStatus !== $status) break;

            $count++;
        }

        return $count;
    }

    private function fullName(?Student $student): string
    {
        return $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) : '';
    }
}
