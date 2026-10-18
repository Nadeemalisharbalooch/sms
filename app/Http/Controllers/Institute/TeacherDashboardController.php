<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\TimetableEntry;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $instituteId = InstituteUser::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->value('institute_id');

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $institute = Institute::find($instituteId);
        $sessionId = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->value('id');

        if ($sessionId === null) {
            return ResponseService::error('No active academic session exists for the active institute', 422);
        }

        $teacherClasses = TimetableEntry::query()
            ->where('session_id', $sessionId)
            ->where('teacher_user_id', $user->id)
            ->whereHas('academicClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->get(['class_id', 'section_id']);

        $pairs = $teacherClasses
            ->unique(fn (TimetableEntry $entry) => $entry->class_id.':'.($entry->section_id ?? 'null'))
            ->values();

        $scopeToTeacherClasses = function ($query) use ($pairs): void {
            $query->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($query) use ($pair) {
                        $query->where('class_id', $pair->class_id);
                        $pair->section_id === null
                            ? $query->whereNull('section_id')
                            : $query->where('section_id', $pair->section_id);
                    });
                }
            });
        };

        $studentCount = $pairs->isEmpty() ? 0 : Enrollment::query()
            ->where('session_id', $sessionId)
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->where($scopeToTeacherClasses)
            ->distinct('student_id')
            ->count('student_id');

        $today = now($institute?->timezone ?: config('app.timezone'));
        $todayAttendanceCount = $pairs->isEmpty() ? 0 : Attendance::query()
            ->where('session_id', $sessionId)
            ->whereDate('date', $today->toDateString())
            ->where($scopeToTeacherClasses)
            ->count();

        $todayLectures = TimetableEntry::query()
            ->where('session_id', $sessionId)
            ->where('teacher_user_id', $user->id)
            ->where('day_of_week', strtolower($today->format('l')))
            ->whereHas('academicClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->count();

        return ResponseService::success([
            'teacher_id' => $user->id,
            'session_id' => (int) $sessionId,
            'date' => $today->toDateString(),
            'total_classes' => $pairs->pluck('class_id')->unique()->count(),
            'total_students' => $studentCount,
            'today_attendance' => $todayAttendanceCount,
            'today_lectures' => $todayLectures,
        ], 'Teacher dashboard summary retrieved successfully');
    }
}
