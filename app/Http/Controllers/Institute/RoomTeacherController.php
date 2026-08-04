<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreRoomTeacherRequest;
use App\Http\Resources\Institute\RoomTeacherResource;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\InstituteUser;
use App\Models\RoomTeacher;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class RoomTeacherController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $roomTeachers = RoomTeacher::query()
            ->with(['session', 'academicClass', 'section', 'teacher'])
            ->whereHas('academicClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->when($request->integer('session_id'), fn ($query, int $sessionId) => $query->where('session_id', $sessionId))
            ->when($request->integer('class_id'), fn ($query, int $classId) => $query->where('class_id', $classId))
            ->when($request->integer('section_id'), fn ($query, int $sectionId) => $query->where('section_id', $sectionId))
            ->latest()
            ->paginate();

        return ResponseService::success(
            RoomTeacherResource::collection($roomTeachers),
            'Room teachers retrieved successfully'
        );
    }

    public function store(StoreRoomTeacherRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $error = $this->roomTeacherError($validated, $instituteId);

        if ($error !== null) {
            return $error;
        }

        $roomTeacher = RoomTeacher::updateOrCreate(
            [
                'session_id' => $validated['session_id'],
                'class_id' => $validated['class_id'],
                'section_id' => $validated['section_id'],
            ],
            [
                'teacher_user_id' => $validated['teacher_id'],
            ]
        );

        return ResponseService::success(
            new RoomTeacherResource($roomTeacher->load(['session', 'academicClass', 'section', 'teacher'])),
            'Room teacher assigned successfully',
            201
        );
    }

    public function show(Request $request, RoomTeacher $roomTeacher)
    {
        if (! $this->belongsToActiveInstitute($request, $roomTeacher)) {
            return ResponseService::notFound('Room teacher assignment not found');
        }

        return ResponseService::success(
            new RoomTeacherResource($roomTeacher->load(['session', 'academicClass', 'section', 'teacher'])),
            'Room teacher assignment retrieved successfully'
        );
    }

    public function destroy(Request $request, RoomTeacher $roomTeacher)
    {
        if (! $this->belongsToActiveInstitute($request, $roomTeacher)) {
            return ResponseService::notFound('Room teacher assignment not found');
        }

        $roomTeacher->delete();

        return ResponseService::success(null, 'Room teacher assignment removed successfully');
    }

    private function roomTeacherError(array $validated, int $instituteId): ?\Illuminate\Http\JsonResponse
    {
        // Validate session belongs to the active institute
        if (! AcademicSession::query()
            ->whereKey($validated['session_id'])
            ->where('institute_id', $instituteId)
            ->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['The selected session must belong to the active institute.'],
            ]);
        }

        // Validate class belongs to the active institute
        if (! AcademicClass::query()
            ->whereKey($validated['class_id'])
            ->where('institute_id', $instituteId)
            ->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'class_id' => ['The selected class must belong to the active institute.'],
            ]);
        }

        // Validate section (if provided) belongs to the class
        if ($validated['section_id'] !== null) {
            if (! AcademicSection::query()
                ->whereKey($validated['section_id'])
                ->where('class_id', $validated['class_id'])
                ->exists()) {
                return ResponseService::error('Validation failed', 422, [
                    'section_id' => ['The selected section must belong to the selected class.'],
                ]);
            }
        }

        // Validate teacher exists and has the correct role
        if (! $this->isInstituteTeacher($validated['teacher_id'], $instituteId)) {
            return ResponseService::error('Validation failed', 422, [
                'teacher_id' => ['The selected user must be an active Teacher in the active institute.'],
            ]);
        }

        return null;
    }

    private function activeInstituteId(Request $request): ?int
    {
        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        return $instituteId === null ? null : (int) $instituteId;
    }

    private function belongsToActiveInstitute(Request $request, RoomTeacher $roomTeacher): bool
    {
        $instituteId = $this->activeInstituteId($request);

        return $instituteId !== null
            && $roomTeacher->academicClass()->where('institute_id', $instituteId)->exists();
    }

    private function isInstituteTeacher(int $teacherId, int $instituteId): bool
    {
        return User::query()
            ->whereKey($teacherId)
            ->whereHas('instituteUsers', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('is_active', true))
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.institute_id', $instituteId)
                ->where('roles.name', 'Teacher'))
            ->exists();
    }
}